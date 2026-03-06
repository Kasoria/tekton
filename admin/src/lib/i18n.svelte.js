const translations = window.tektonData?.translations || {};

export function t(key, fallback) {
	return translations[key] || fallback || key;
}
