<?php
declare(strict_types=1);
/**
 * CRUD for page structures, versions, and chat history.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Storage {

	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables = [];

		$tables[] = "CREATE TABLE {$wpdb->prefix}tekton_structures (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			template_key VARCHAR(100) NOT NULL,
			title VARCHAR(255) DEFAULT '',
			components LONGTEXT NOT NULL,
			styles LONGTEXT DEFAULT NULL,
			status VARCHAR(20) DEFAULT 'draft',
			active_version INT DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY template_key (template_key)
		) {$charset};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}tekton_versions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			structure_id BIGINT UNSIGNED NOT NULL,
			version_number INT NOT NULL,
			components LONGTEXT NOT NULL,
			styles LONGTEXT DEFAULT NULL,
			change_type VARCHAR(50) DEFAULT 'ai_generate',
			change_summary TEXT DEFAULT NULL,
			label VARCHAR(255) DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY structure_version (structure_id, version_number)
		) {$charset};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}tekton_chat_history (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			template_key VARCHAR(100) NOT NULL,
			role VARCHAR(20) NOT NULL,
			content LONGTEXT NOT NULL,
			metadata LONGTEXT DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY template_key (template_key)
		) {$charset};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}tekton_field_groups (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			slug VARCHAR(100) NOT NULL,
			fields LONGTEXT NOT NULL,
			location_rules LONGTEXT NOT NULL,
			position VARCHAR(20) DEFAULT 'normal',
			priority VARCHAR(20) DEFAULT 'high',
			menu_order INT DEFAULT 0,
			is_active TINYINT(1) DEFAULT 1,
			source VARCHAR(20) DEFAULT 'ai',
			ai_prompt TEXT DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug)
		) {$charset};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}tekton_post_types (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(20) NOT NULL,
			config LONGTEXT NOT NULL,
			taxonomies LONGTEXT DEFAULT NULL,
			source VARCHAR(20) DEFAULT 'ai',
			ai_prompt TEXT DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug)
		) {$charset};";

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
	}

	public function get_structure( string $template_key ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}tekton_structures WHERE template_key = %s",
				$template_key
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$row['components'] = json_decode( $row['components'], true ) ?? [];
		$row['styles']     = json_decode( $row['styles'] ?? '{}', true ) ?? [];

		// Extract keyframes and scripts from styles storage to top level.
		if ( ! empty( $row['styles']['keyframes'] ) ) {
			$row['keyframes'] = $row['styles']['keyframes'];
			unset( $row['styles']['keyframes'] );
		}
		if ( ! empty( $row['styles']['scripts'] ) ) {
			$row['scripts'] = $row['styles']['scripts'];
			unset( $row['styles']['scripts'] );
		}
		if ( ! empty( $row['styles']['meta'] ) ) {
			$row['meta'] = $row['styles']['meta'];
			unset( $row['styles']['meta'] );
		}
		if ( ! empty( $row['styles']['wrapper_styles'] ) ) {
			$row['wrapper_styles'] = $row['styles']['wrapper_styles'];
			unset( $row['styles']['wrapper_styles'] );
		}

		return $row;
	}

	public function save_structure( string $template_key, array $data ): int {
		global $wpdb;

		$table    = $wpdb->prefix . 'tekton_structures';
		$existing = $this->get_structure( $template_key );

		$styles_data = $data['styles'] ?? [];
		if ( ! empty( $data['keyframes'] ) ) {
			$styles_data['keyframes'] = $data['keyframes'];
		}
		if ( ! empty( $data['scripts'] ) ) {
			$styles_data['scripts'] = $data['scripts'];
		}
		if ( ! empty( $data['meta'] ) ) {
			$styles_data['meta'] = $data['meta'];
		}
		if ( ! empty( $data['wrapper_styles'] ) ) {
			$styles_data['wrapper_styles'] = $data['wrapper_styles'];
		}

		$row_data = [
			'template_key' => $template_key,
			'title'        => $data['title'] ?? '',
			'components'   => wp_json_encode( $data['components'] ?? [] ),
			'styles'       => wp_json_encode( $styles_data ),
			'status'       => $data['status'] ?? 'draft',
		];

		if ( $existing ) {
			$wpdb->update( $table, $row_data, [ 'template_key' => $template_key ] );
			$structure_id = (int) $existing['id'];
		} else {
			$wpdb->insert( $table, $row_data );
			$structure_id = (int) $wpdb->insert_id;
		}

		$data['styles'] = $styles_data;
		$version_number = $this->create_version( $structure_id, $data );

		// Point active_version to the newly created version.
		$wpdb->update( $table, [ 'active_version' => $version_number ], [ 'id' => $structure_id ] );

		return $structure_id;
	}

	public function delete_structure( string $template_key ): bool {
		global $wpdb;

		$structure = $this->get_structure( $template_key );
		if ( ! $structure ) {
			return false;
		}

		$wpdb->delete(
			$wpdb->prefix . 'tekton_versions',
			[ 'structure_id' => $structure['id'] ]
		);

		return (bool) $wpdb->delete(
			$wpdb->prefix . 'tekton_structures',
			[ 'template_key' => $template_key ]
		);
	}

	public function list_structures(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT id, template_key, title, status, created_at, updated_at
			 FROM {$wpdb->prefix}tekton_structures
			 ORDER BY updated_at DESC",
			ARRAY_A
		);

		return $rows ?: [];
	}

	public function get_versions( int $structure_id, int $limit = 20 ): array {
		global $wpdb;

		// Get active_version for this structure.
		$active = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT active_version FROM {$wpdb->prefix}tekton_structures WHERE id = %d",
				$structure_id
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, version_number, change_type, change_summary, label, created_at
				 FROM {$wpdb->prefix}tekton_versions
				 WHERE structure_id = %d
				 ORDER BY version_number DESC
				 LIMIT %d",
				$structure_id,
				$limit
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return [];
		}

		// Mark which version is active.
		foreach ( $rows as &$row ) {
			$row['is_active'] = ( (int) $row['version_number'] === $active );
		}
		unset( $row );

		return $rows;
	}

	public function rollback( int $structure_id, int $version_number ): bool {
		global $wpdb;

		$version = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT components, styles FROM {$wpdb->prefix}tekton_versions
				 WHERE structure_id = %d AND version_number = %d",
				$structure_id,
				$version_number
			),
			ARRAY_A
		);

		if ( ! $version ) {
			return false;
		}

		// Restore structure to this version and update the active pointer. No new version created.
		$wpdb->update(
			$wpdb->prefix . 'tekton_structures',
			[
				'components'     => $version['components'],
				'styles'         => $version['styles'],
				'active_version' => $version_number,
			],
			[ 'id' => $structure_id ]
		);

		return true;
	}

	public function rename_version( int $structure_id, int $version_number, string $label ): bool {
		global $wpdb;

		return false !== $wpdb->update(
			$wpdb->prefix . 'tekton_versions',
			[ 'label' => $label ],
			[
				'structure_id'   => $structure_id,
				'version_number' => $version_number,
			]
		);
	}

	public function get_chat_history( string $template_key, int $limit = 50 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, role, content, metadata, created_at
				 FROM {$wpdb->prefix}tekton_chat_history
				 WHERE template_key = %s
				 ORDER BY created_at ASC
				 LIMIT %d",
				$template_key,
				$limit
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return [];
		}

		// Decode metadata JSON for each row.
		foreach ( $rows as &$row ) {
			if ( ! empty( $row['metadata'] ) ) {
				$row['metadata'] = json_decode( $row['metadata'], true ) ?: null;
			} else {
				$row['metadata'] = null;
			}
		}
		unset( $row );

		return $rows;
	}

	public function add_chat_message( string $template_key, string $role, string $content, ?array $metadata = null ): int {
		global $wpdb;

		$data = [
			'template_key' => $template_key,
			'role'         => $role,
			'content'      => $content,
		];

		if ( $metadata ) {
			$data['metadata'] = wp_json_encode( $metadata );
		}

		$wpdb->insert( $wpdb->prefix . 'tekton_chat_history', $data );

		return (int) $wpdb->insert_id;
	}

	public function clear_chat_history( string $template_key ): bool {
		global $wpdb;

		return (bool) $wpdb->delete(
			$wpdb->prefix . 'tekton_chat_history',
			[ 'template_key' => $template_key ]
		);
	}

	// ─── Field Groups ──────────────────────────────────────────────────

	public function list_field_groups(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT id, title, slug, fields, location_rules, source, created_at, updated_at
			 FROM {$wpdb->prefix}tekton_field_groups
			 WHERE is_active = 1
			 ORDER BY menu_order ASC, title ASC",
			ARRAY_A
		);

		if ( ! $rows ) {
			return [];
		}

		foreach ( $rows as &$row ) {
			$row['fields']         = json_decode( $row['fields'], true ) ?? [];
			$row['location_rules'] = json_decode( $row['location_rules'], true ) ?? [];
			$row['field_count']    = count( $row['fields'] );
		}

		return $rows;
	}

	// ─── Post Types ────────────────────────────────────────────────────

	public function list_post_types(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT id, slug, config, taxonomies, source, created_at, updated_at
			 FROM {$wpdb->prefix}tekton_post_types
			 ORDER BY slug ASC",
			ARRAY_A
		);

		if ( ! $rows ) {
			return [];
		}

		foreach ( $rows as &$row ) {
			$row['config']     = json_decode( $row['config'], true ) ?? [];
			$row['taxonomies'] = json_decode( $row['taxonomies'] ?? '[]', true ) ?? [];
		}

		return $rows;
	}

	// ─── Activity ──────────────────────────────────────────────────────

	/**
	 * Get recent activity across all tables.
	 */
	public function get_recent_activity( int $limit = 10 ): array {
		global $wpdb;

		$activity = [];

		// Recent structure changes from versions table.
		$versions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT v.change_type, v.change_summary, v.created_at, s.template_key, s.title
				 FROM {$wpdb->prefix}tekton_versions v
				 JOIN {$wpdb->prefix}tekton_structures s ON v.structure_id = s.id
				 ORDER BY v.created_at DESC
				 LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		foreach ( $versions ?: [] as $v ) {
			$action = match ( $v['change_type'] ) {
				'ai_generate' => 'Generated',
				'rollback'    => 'Rolled back',
				'manual'      => 'Updated',
				default       => 'Modified',
			};
			$activity[] = [
				'action' => $action,
				'target' => $v['title'] ?: $v['template_key'],
				'time'   => $v['created_at'],
				'kind'   => 'template',
			];
		}

		// Sort by time descending and limit.
		usort( $activity, fn( $a, $b ) => strtotime( $b['time'] ) - strtotime( $a['time'] ) );

		return array_slice( $activity, 0, $limit );
	}

	private function create_version( int $structure_id, array $data ): int {
		global $wpdb;

		$max_version = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(MAX(version_number), 0)
				 FROM {$wpdb->prefix}tekton_versions
				 WHERE structure_id = %d",
				$structure_id
			)
		);

		$new_version = $max_version + 1;

		$wpdb->insert(
			$wpdb->prefix . 'tekton_versions',
			[
				'structure_id'   => $structure_id,
				'version_number' => $new_version,
				'components'     => wp_json_encode( $data['components'] ?? [] ),
				'styles'         => wp_json_encode( $data['styles'] ?? [] ),
				'change_type'    => $data['change_type'] ?? 'ai_generate',
				'change_summary' => $data['change_summary'] ?? null,
			]
		);

		return $new_version;
	}
}
