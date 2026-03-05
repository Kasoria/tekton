import { useState } from "react";

/*
  TEKTON DASHBOARD — "The Architect's Workbench" continued
  This is the wp-admin landing page. Same warm stone/copper palette.
  Layout: editorial-inspired, generous whitespace, asymmetric grid.
*/

const TEMPLATES = [
  { key: "front-page", label: "Homepage", status: "published", v: 7, edit: "2h ago", comps: 14 },
  { key: "single-post", label: "Blog Post", status: "published", v: 3, edit: "1d ago", comps: 8 },
  { key: "archive-team_member", label: "Team Archive", status: "draft", v: 1, edit: "3h ago", comps: 6 },
  { key: "single-team_member", label: "Team Single", status: "draft", v: 2, edit: "3h ago", comps: 11 },
  { key: "archive-post", label: "Blog Archive", status: "published", v: 5, edit: "2d ago", comps: 9 },
  { key: "global-header", label: "Global Header", status: "published", v: 4, edit: "1d ago", comps: 6 },
  { key: "global-footer", label: "Global Footer", status: "published", v: 3, edit: "2d ago", comps: 7 },
  { key: "404", label: "404 Page", status: "published", v: 2, edit: "5d ago", comps: 3 },
];

const FIELD_GROUPS = [
  { slug: "homepage_hero", title: "Homepage Hero", fields: 5, loc: "page: Homepage", src: "ai" },
  { slug: "team_member_details", title: "Team Member Details", fields: 8, loc: "CPT: team_member", src: "ai" },
  { slug: "site_settings", title: "Site Settings", fields: 6, loc: "Options Page", src: "ai" },
  { slug: "blog_extras", title: "Blog Post Extras", fields: 3, loc: "post", src: "manual" },
];

const CPTS = [
  { slug: "team_member", label: "Team Members", count: 12, tax: ["department"], src: "ai" },
  { slug: "testimonial", label: "Testimonials", count: 8, tax: [], src: "ai" },
  { slug: "project", label: "Projects", count: 15, tax: ["project_type"], src: "manual" },
];

const ACTIVITY = [
  { action: "Published", target: "Homepage", time: "2h ago", kind: "template" },
  { action: "Generated", target: "Team Member CPT + Fields", time: "3h ago", kind: "fullstack" },
  { action: "Created", target: "Contact Form plugin", time: "Yesterday", kind: "plugin" },
  { action: "Updated", target: "Blog Post template v3", time: "1d ago", kind: "template" },
  { action: "Modified", target: "Design tokens — colors", time: "2d ago", kind: "tokens" },
];

const kindHue = { template: "#c97d3c", fullstack: "#b86e4a", plugin: "#c9a43c", tokens: "#6b7d8a" };

export default function TektonDashboard() {
  const [tab, setTab] = useState("overview");

  return (
    <div style={{
      minHeight: "100vh", background: "#1a1816", color: "#ede8e3",
      fontFamily: "'Outfit', sans-serif"
    }}>
      <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,700;12..96,800&family=Outfit:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet" />

      {/* Grain */}
      <div style={{
        position: "fixed", inset: 0, pointerEvents: "none", zIndex: 999, opacity: 0.022, mixBlendMode: "overlay",
        backgroundImage: `url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E")`,
      }} />

      {/* HEADER */}
      <header style={{
        borderBottom: "1px solid #2a2725", padding: "0 40px",
        background: "#1e1c19", position: "sticky", top: 0, zIndex: 10
      }}>
        <div style={{
          maxWidth: 1120, margin: "0 auto", height: 60,
          display: "flex", alignItems: "center", justifyContent: "space-between"
        }}>
          <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
              <rect x="2" y="6" width="20" height="2" rx="1" fill="#c97d3c"/>
              <rect x="5" y="10" width="14" height="2" rx="1" fill="#c97d3c" opacity="0.6"/>
              <rect x="8" y="14" width="8" height="2" rx="1" fill="#c97d3c" opacity="0.35"/>
              <rect x="10" y="18" width="4" height="2" rx="1" fill="#c97d3c" opacity="0.2"/>
            </svg>
            <div>
              <span style={{ fontFamily: "'Bricolage Grotesque'", fontSize: 18, fontWeight: 800, letterSpacing: -0.5 }}>tekton</span>
              <span style={{ fontSize: 10, color: "#5c5753", marginLeft: 8, fontWeight: 400 }}>v1.0.0</span>
            </div>
          </div>
          <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
            <div style={{
              display: "flex", alignItems: "center", gap: 5, padding: "4px 10px",
              borderRadius: 5, background: "#7dab6e10", border: "1px solid #7dab6e20"
            }}>
              <div style={{ width: 5, height: 5, borderRadius: 3, background: "#7dab6e" }} />
              <span style={{ fontSize: 11, color: "#7dab6e", fontWeight: 500 }}>API Connected</span>
            </div>
            <button style={{
              padding: "7px 20px", borderRadius: 7, border: "none", cursor: "pointer",
              background: "#c97d3c", color: "#1a1816", fontSize: 13, fontWeight: 600,
              fontFamily: "'Outfit'"
            }}>Open Builder</button>
          </div>
        </div>
      </header>

      <div style={{ maxWidth: 1120, margin: "0 auto", padding: "0 40px" }}>

        {/* NAV TABS */}
        <nav style={{
          display: "flex", gap: 0, borderBottom: "1px solid #2a2725",
          marginBottom: 32, paddingTop: 4
        }}>
          {[
            { key: "overview", label: "Overview" },
            { key: "templates", label: "Templates" },
            { key: "fields", label: "Fields" },
            { key: "cpts", label: "Post Types" },
            { key: "settings", label: "Settings" },
          ].map(t => (
            <button key={t.key} onClick={() => setTab(t.key)} style={{
              padding: "12px 20px", background: "transparent", border: "none",
              borderBottom: tab === t.key ? "2px solid #c97d3c" : "2px solid transparent",
              color: tab === t.key ? "#ede8e3" : "#5c5753",
              fontSize: 13, fontWeight: 500, cursor: "pointer",
              fontFamily: "'Outfit'", transition: "0.15s", marginBottom: -1
            }}>{t.label}</button>
          ))}
        </nav>

        {/* ═══ OVERVIEW ═══ */}
        {tab === "overview" && (
          <div style={{ paddingBottom: 60 }}>

            {/* Hero stat row */}
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr 1fr", gap: 14, marginBottom: 40 }}>
              {[
                { n: TEMPLATES.length, label: "Templates", sub: `${TEMPLATES.filter(t=>t.status==="published").length} live` },
                { n: FIELD_GROUPS.length, label: "Field Groups", sub: `${FIELD_GROUPS.reduce((a,f)=>a+f.fields,0)} fields` },
                { n: CPTS.length, label: "Post Types", sub: `${CPTS.reduce((a,c)=>a+c.count,0)} entries` },
                { n: 2, label: "Plugins", sub: "2 active" },
              ].map((s, i) => (
                <div key={i} style={{
                  padding: "22px 24px", borderRadius: 10,
                  border: "1px solid #2a2725", background: "#1e1c19",
                  position: "relative", overflow: "hidden"
                }}>
                  <div style={{
                    position: "absolute", top: 0, right: 0, width: 80, height: 80,
                    background: "radial-gradient(circle at top right, #c97d3c08, transparent 70%)"
                  }} />
                  <div style={{
                    fontFamily: "'Bricolage Grotesque'", fontSize: 40, fontWeight: 800,
                    color: "#ede8e3", lineHeight: 1, letterSpacing: -2
                  }}>{s.n}</div>
                  <div style={{ fontSize: 12, color: "#8a847d", marginTop: 6, fontWeight: 500 }}>{s.label}</div>
                  <div style={{ fontSize: 11, color: "#3a3835", marginTop: 2 }}>{s.sub}</div>
                </div>
              ))}
            </div>

            {/* Two-column: Templates + Activity */}
            <div style={{ display: "grid", gridTemplateColumns: "1fr 340px", gap: 20, marginBottom: 32 }}>

              {/* Templates list */}
              <div>
                <div style={{ display: "flex", alignItems: "baseline", justifyContent: "space-between", marginBottom: 14 }}>
                  <h2 style={{
                    fontFamily: "'Bricolage Grotesque'", fontSize: 20, fontWeight: 700,
                    letterSpacing: -0.5, margin: 0
                  }}>Templates</h2>
                  <button onClick={() => setTab("templates")} style={{
                    background: "transparent", border: "none", color: "#c97d3c",
                    cursor: "pointer", fontSize: 12, fontWeight: 500, fontFamily: "'Outfit'"
                  }}>View all →</button>
                </div>

                <div style={{
                  borderRadius: 10, border: "1px solid #2a2725",
                  background: "#1e1c19", overflow: "hidden"
                }}>
                  {TEMPLATES.slice(0, 6).map((t, i) => (
                    <div key={t.key} style={{
                      display: "flex", alignItems: "center", justifyContent: "space-between",
                      padding: "12px 18px",
                      borderBottom: i < 5 ? "1px solid #2a272540" : "none",
                      cursor: "pointer", transition: "background 0.1s"
                    }}
                    onMouseEnter={e => e.currentTarget.style.background = "#242220"}
                    onMouseLeave={e => e.currentTarget.style.background = "transparent"}
                    >
                      <div style={{ display: "flex", alignItems: "center", gap: 14 }}>
                        <div style={{
                          width: 3, height: 28, borderRadius: 2,
                          background: t.status === "published" ? "#c97d3c" : "#2a2725"
                        }} />
                        <div>
                          <div style={{ fontSize: 13, fontWeight: 500 }}>{t.label}</div>
                          <div style={{ fontSize: 10, color: "#5c5753", fontFamily: "'Fira Code'", marginTop: 1 }}>{t.key}</div>
                        </div>
                      </div>
                      <div style={{ display: "flex", alignItems: "center", gap: 16 }}>
                        <span style={{ fontSize: 11, color: "#3a3835" }}>{t.comps} comp</span>
                        <span style={{ fontSize: 11, color: "#3a3835" }}>{t.edit}</span>
                        <span style={{
                          fontSize: 10, fontWeight: 600, color: t.status === "published" ? "#7dab6e" : "#c9a43c"
                        }}>
                          {t.status === "published" ? "LIVE" : "DRAFT"}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Activity */}
              <div>
                <h2 style={{
                  fontFamily: "'Bricolage Grotesque'", fontSize: 20, fontWeight: 700,
                  letterSpacing: -0.5, margin: 0, marginBottom: 14
                }}>Activity</h2>
                <div style={{
                  borderRadius: 10, border: "1px solid #2a2725",
                  background: "#1e1c19", padding: "8px 0"
                }}>
                  {ACTIVITY.map((a, i) => (
                    <div key={i} style={{ display: "flex", gap: 12, padding: "10px 18px", alignItems: "flex-start" }}>
                      <div style={{
                        width: 3, height: 3, borderRadius: 2, marginTop: 7, flexShrink: 0,
                        background: kindHue[a.kind] || "#5c5753"
                      }} />
                      <div style={{ flex: 1 }}>
                        <div style={{ fontSize: 12, color: "#8a847d", lineHeight: 1.5 }}>
                          <span style={{ color: "#5c5753" }}>{a.action}</span>{" "}
                          <span style={{ color: "#c4bdb5", fontWeight: 500 }}>{a.target}</span>
                        </div>
                        <div style={{ fontSize: 10, color: "#3a3835", marginTop: 1 }}>{a.time}</div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* Quick actions */}
            <div>
              <h2 style={{
                fontFamily: "'Bricolage Grotesque'", fontSize: 20, fontWeight: 700,
                letterSpacing: -0.5, margin: 0, marginBottom: 14
              }}>Quick Actions</h2>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 14 }}>
                {[
                  { label: "Build a Page", desc: "Start from a natural language prompt", badge: "AI" },
                  { label: "Full-Stack Generate", desc: "CPT + Fields + Template in one shot", badge: "AI" },
                  { label: "Create Plugin", desc: "Generate a server-side feature", badge: "Plugin Mode" },
                ].map((a, i) => (
                  <button key={i} style={{
                    padding: "24px 22px", borderRadius: 10, textAlign: "left",
                    border: "1px solid #2a2725", background: "#1e1c19",
                    cursor: "pointer", transition: "border-color 0.2s", position: "relative",
                    overflow: "hidden"
                  }}
                  onMouseEnter={e => e.currentTarget.style.borderColor = "#3a3835"}
                  onMouseLeave={e => e.currentTarget.style.borderColor = "#2a2725"}
                  >
                    <div style={{
                      position: "absolute", bottom: -20, right: -20, width: 100, height: 100,
                      background: "radial-gradient(circle, #c97d3c06, transparent 70%)"
                    }} />
                    <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 8 }}>
                      <span style={{
                        fontFamily: "'Bricolage Grotesque'", fontSize: 16, fontWeight: 700,
                        color: "#ede8e3"
                      }}>{a.label}</span>
                      <span style={{
                        padding: "2px 7px", borderRadius: 4, fontSize: 9, fontWeight: 600,
                        background: "#c97d3c15", color: "#c97d3c", textTransform: "uppercase",
                        letterSpacing: 0.5
                      }}>{a.badge}</span>
                    </div>
                    <div style={{ fontSize: 12, color: "#5c5753", lineHeight: 1.5 }}>{a.desc}</div>
                  </button>
                ))}
              </div>
            </div>
          </div>
        )}

        {/* ═══ TEMPLATES ═══ */}
        {tab === "templates" && (
          <div style={{ paddingBottom: 60 }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline", marginBottom: 24 }}>
              <div>
                <h2 style={{ fontFamily: "'Bricolage Grotesque'", fontSize: 24, fontWeight: 800, letterSpacing: -0.5, margin: 0 }}>Templates</h2>
                <p style={{ fontSize: 13, color: "#5c5753", marginTop: 4 }}>Page and archive templates managed by Tekton</p>
              </div>
              <button style={{
                padding: "7px 18px", borderRadius: 7, border: "none",
                background: "#c97d3c", color: "#1a1816", cursor: "pointer",
                fontSize: 12, fontWeight: 600, fontFamily: "'Outfit'"
              }}>+ New Template</button>
            </div>

            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 14 }}>
              {TEMPLATES.map(t => (
                <div key={t.key} style={{
                  borderRadius: 10, border: "1px solid #2a2725", background: "#1e1c19",
                  cursor: "pointer", transition: "border-color 0.2s", overflow: "hidden"
                }}
                onMouseEnter={e => e.currentTarget.style.borderColor = "#3a3835"}
                onMouseLeave={e => e.currentTarget.style.borderColor = "#2a2725"}
                >
                  {/* Skeleton preview */}
                  <div style={{
                    height: 90, background: "#1a1816", margin: 10, borderRadius: 6,
                    display: "flex", flexDirection: "column", padding: 8, gap: 4,
                    border: "1px solid #2a272530"
                  }}>
                    <div style={{ height: 5, width: "60%", background: "#2a2725", borderRadius: 2 }} />
                    <div style={{ flex: 1, display: "flex", gap: 4 }}>
                      <div style={{ flex: 2, background: "#2a272540", borderRadius: 3 }} />
                      <div style={{ flex: 1, display: "flex", flexDirection: "column", gap: 3 }}>
                        <div style={{ flex: 1, background: "#2a272530", borderRadius: 2 }} />
                        <div style={{ flex: 1, background: "#2a272530", borderRadius: 2 }} />
                      </div>
                    </div>
                  </div>
                  <div style={{ padding: "8px 14px 14px" }}>
                    <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
                      <span style={{ fontSize: 14, fontWeight: 600 }}>{t.label}</span>
                      <span style={{
                        fontSize: 10, fontWeight: 600,
                        color: t.status === "published" ? "#7dab6e" : "#c9a43c"
                      }}>{t.status === "published" ? "LIVE" : "DRAFT"}</span>
                    </div>
                    <div style={{ fontSize: 10, color: "#5c5753", fontFamily: "'Fira Code'", marginTop: 3 }}>{t.key}</div>
                    <div style={{ display: "flex", gap: 10, marginTop: 8, fontSize: 11, color: "#3a3835" }}>
                      <span>v{t.v}</span>
                      <span>·</span>
                      <span>{t.comps} components</span>
                      <span>·</span>
                      <span>{t.edit}</span>
                    </div>
                  </div>
                </div>
              ))}
              <div style={{
                borderRadius: 10, border: "1px dashed #2a2725", minHeight: 170,
                display: "flex", flexDirection: "column", alignItems: "center",
                justifyContent: "center", gap: 8, cursor: "pointer"
              }}>
                <div style={{
                  width: 36, height: 36, borderRadius: 8, background: "#242220",
                  display: "flex", alignItems: "center", justifyContent: "center",
                  color: "#5c5753", fontSize: 18
                }}>+</div>
                <span style={{ fontSize: 12, color: "#5c5753" }}>New Template</span>
              </div>
            </div>
          </div>
        )}

        {/* ═══ FIELDS ═══ */}
        {tab === "fields" && (
          <div style={{ paddingBottom: 60 }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline", marginBottom: 24 }}>
              <div>
                <h2 style={{ fontFamily: "'Bricolage Grotesque'", fontSize: 24, fontWeight: 800, letterSpacing: -0.5, margin: 0 }}>Field Groups</h2>
                <p style={{ fontSize: 13, color: "#5c5753", marginTop: 4 }}>Content structure — Tekton's built-in field engine</p>
              </div>
              <button style={{
                padding: "7px 18px", borderRadius: 7, border: "none",
                background: "#c97d3c", color: "#1a1816", cursor: "pointer",
                fontSize: 12, fontWeight: 600, fontFamily: "'Outfit'"
              }}>+ New Field Group</button>
            </div>

            <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
              {FIELD_GROUPS.map(fg => (
                <div key={fg.slug} style={{
                  display: "flex", alignItems: "center", justifyContent: "space-between",
                  padding: "16px 20px", borderRadius: 10, border: "1px solid #2a2725",
                  background: "#1e1c19", cursor: "pointer", transition: "border-color 0.15s"
                }}
                onMouseEnter={e => e.currentTarget.style.borderColor = "#3a3835"}
                onMouseLeave={e => e.currentTarget.style.borderColor = "#2a2725"}
                >
                  <div style={{ display: "flex", alignItems: "center", gap: 16 }}>
                    <div style={{
                      width: 3, height: 32, borderRadius: 2,
                      background: fg.src === "ai" ? "#c97d3c" : "#6b7d8a"
                    }} />
                    <div>
                      <div style={{ fontSize: 14, fontWeight: 600 }}>{fg.title}</div>
                      <div style={{ display: "flex", gap: 8, marginTop: 3, alignItems: "center" }}>
                        <span style={{ fontSize: 11, color: "#5c5753", fontFamily: "'Fira Code'" }}>{fg.slug}</span>
                        <span style={{ color: "#2a2725" }}>·</span>
                        <span style={{ fontSize: 11, color: "#5c5753" }}>{fg.loc}</span>
                      </div>
                    </div>
                  </div>
                  <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
                    <span style={{ fontSize: 12, color: "#5c5753" }}>{fg.fields} fields</span>
                    <span style={{
                      padding: "3px 8px", borderRadius: 4, fontSize: 10, fontWeight: 600,
                      background: fg.src === "ai" ? "#c97d3c12" : "#6b7d8a12",
                      color: fg.src === "ai" ? "#c97d3c" : "#6b7d8a",
                      textTransform: "uppercase", letterSpacing: 0.5
                    }}>{fg.src}</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* ═══ POST TYPES ═══ */}
        {tab === "cpts" && (
          <div style={{ paddingBottom: 60 }}>
            <h2 style={{ fontFamily: "'Bricolage Grotesque'", fontSize: 24, fontWeight: 800, letterSpacing: -0.5, margin: 0, marginBottom: 24 }}>Custom Post Types</h2>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 14 }}>
              {CPTS.map(c => (
                <div key={c.slug} style={{
                  padding: "22px 20px", borderRadius: 10, border: "1px solid #2a2725",
                  background: "#1e1c19"
                }}>
                  <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 12 }}>
                    <span style={{ fontFamily: "'Bricolage Grotesque'", fontSize: 16, fontWeight: 700 }}>{c.label}</span>
                    <span style={{
                      padding: "3px 8px", borderRadius: 4, fontSize: 10, fontWeight: 600,
                      background: c.src === "ai" ? "#c97d3c12" : "#6b7d8a12",
                      color: c.src === "ai" ? "#c97d3c" : "#6b7d8a",
                      textTransform: "uppercase", letterSpacing: 0.5
                    }}>{c.src}</span>
                  </div>
                  <div style={{ fontSize: 11, color: "#5c5753", fontFamily: "'Fira Code'", marginBottom: 10 }}>{c.slug}</div>
                  <div style={{ display: "flex", gap: 8, fontSize: 12, color: "#8a847d" }}>
                    <span>{c.count} entries</span>
                    {c.tax.length > 0 && <>
                      <span style={{ color: "#2a2725" }}>·</span>
                      <span>{c.tax.join(", ")}</span>
                    </>}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* ═══ SETTINGS ═══ */}
        {tab === "settings" && (
          <div style={{ maxWidth: 560, paddingBottom: 60 }}>
            <h2 style={{ fontFamily: "'Bricolage Grotesque'", fontSize: 24, fontWeight: 800, letterSpacing: -0.5, margin: 0, marginBottom: 24 }}>Settings</h2>

            {[
              { title: "AI", rows: [
                { label: "API Key", val: "sk-ant-••••••kF7w", type: "pw" },
                { label: "Model", val: "claude-sonnet-4-20250514", type: "text" },
                { label: "Max tokens", val: "8192", type: "text" },
              ]},
              { title: "Rendering", rows: [
                { label: "Override theme", val: true, type: "bool" },
                { label: "Disable Gutenberg", val: true, type: "bool" },
                { label: "Cache HTML", val: true, type: "bool" },
                { label: "Minify output", val: true, type: "bool" },
              ]},
              { title: "Optional", rows: [
                { label: "Admin Customizer", val: false, type: "bool" },
                { label: "ACF compatibility", val: true, type: "bool" },
                { label: "Debug mode", val: false, type: "bool" },
              ]},
            ].map((sec, si) => (
              <div key={si} style={{
                marginBottom: 16, borderRadius: 10, border: "1px solid #2a2725",
                background: "#1e1c19", overflow: "hidden"
              }}>
                <div style={{
                  padding: "10px 18px", borderBottom: "1px solid #2a2725",
                  fontSize: 11, fontWeight: 600, textTransform: "uppercase",
                  letterSpacing: 1.5, color: "#5c5753"
                }}>{sec.title}</div>
                {sec.rows.map((r, ri) => (
                  <div key={ri} style={{
                    display: "flex", alignItems: "center", justifyContent: "space-between",
                    padding: "10px 18px",
                    borderBottom: ri < sec.rows.length - 1 ? "1px solid #2a272530" : "none"
                  }}>
                    <span style={{ fontSize: 13, color: "#c4bdb5" }}>{r.label}</span>
                    {r.type === "bool" ? (
                      <div style={{
                        width: 32, height: 18, borderRadius: 9, padding: 2, cursor: "pointer",
                        background: r.val ? "#c97d3c" : "#2a2725", transition: "0.2s",
                        display: "flex", alignItems: "center",
                        justifyContent: r.val ? "flex-end" : "flex-start"
                      }}>
                        <div style={{ width: 14, height: 14, borderRadius: 7, background: r.val ? "#1a1816" : "#5c5753" }} />
                      </div>
                    ) : (
                      <span style={{
                        fontSize: 12, color: "#5c5753",
                        fontFamily: r.type === "pw" ? "'Fira Code'" : "'Outfit'",
                        padding: "4px 10px", background: "#1a1816",
                        borderRadius: 5, border: "1px solid #2a2725"
                      }}>{r.val}</span>
                    )}
                  </div>
                ))}
              </div>
            ))}
          </div>
        )}

      </div>

      <style>{`
        * { box-sizing: border-box; margin: 0; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #2a2725; border-radius: 3px; }
      `}</style>
    </div>
  );
}