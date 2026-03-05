import { useState, useRef, useEffect } from "react";

/*
  TEKTON BUILDER v3 — "The Architect's Workbench"
  
  Two-zone layout: Chat (left) + Preview (right)
  Third zone (sidebar right) opens on demand for Tree/Fields/Versions
  Contextual suggested actions live inline in the chat
  Build progress cards live in the conversation flow
  No floating bottom bar. Clean, focused.
*/

const TYPE_MAP = {
  section: { letter: "S", hue: "#c97d3c" },
  container: { letter: "C", hue: "#8a7d6b" },
  heading: { letter: "H", hue: "#b86e4a" },
  text: { letter: "T", hue: "#7d8a6b" },
  button: { letter: "B", hue: "#c9a43c" },
  grid: { letter: "G", hue: "#6b7d8a" },
  image: { letter: "I", hue: "#7dab6e" },
};

const TREE = [
  { id: "c_h1", type: "section", label: "Hero", depth: 0 },
  { id: "c_h2", type: "container", label: "Hero Inner", depth: 1 },
  { id: "c_h3", type: "heading", label: "Headline", depth: 2 },
  { id: "c_h4", type: "text", label: "Subtitle", depth: 2 },
  { id: "c_h5", type: "button", label: "CTA", depth: 2 },
  { id: "c_f1", type: "section", label: "Features", depth: 0 },
  { id: "c_f2", type: "heading", label: "Section Title", depth: 1 },
  { id: "c_f3", type: "grid", label: "Card Grid", depth: 1 },
  { id: "c_f4", type: "container", label: "Card 1", depth: 2 },
  { id: "c_f5", type: "container", label: "Card 2", depth: 2 },
  { id: "c_f6", type: "container", label: "Card 3", depth: 2 },
  { id: "c_c1", type: "section", label: "CTA Banner", depth: 0 },
];

const VERSIONS = [
  { v: 7, prompt: "Premium features section", time: "3m ago", current: true },
  { v: 6, prompt: "Initial landing page build", time: "8m ago" },
  { v: 5, prompt: "Hero background to dark", time: "12m ago" },
  { v: 4, prompt: "Added CTA banner bottom", time: "18m ago" },
];

const FIELDS = [
  { title: "Homepage Hero", slug: "homepage_hero", fields: ["headline", "subtitle", "cta_text", "cta_url", "bg_image"] },
  { title: "Site Settings", slug: "site_settings", fields: ["company_name", "phone", "email", "address"] },
];

const PAGES = [
  { key: "front-page", label: "Homepage", status: "published", v: 7 },
  { key: "single-post", label: "Blog Post", status: "published", v: 3 },
  { key: "archive-team", label: "Team Archive", status: "draft", v: 1 },
  { key: "global-header", label: "Header", status: "published", v: 4 },
  { key: "global-footer", label: "Footer", status: "published", v: 3 },
  { key: "404", label: "404", status: "draft", v: 0 },
];

const INIT_MESSAGES = [
  {
    role: "user",
    content: "Create a landing page for CloudSync — a developer tool for real-time file syncing. Hero with headline, subtitle, and CTA. Features section with 3 cards."
  },
  {
    role: "assistant",
    content: "Your landing page is live. Here's what I built:",
    buildCard: {
      title: "CloudSync Landing Page",
      steps: [
        { label: "Set up design tokens", done: true },
        { label: "Build hero section", done: true },
        { label: "Create features grid", done: true },
        { label: "Generate field group", done: true },
      ]
    },
    details: [
      { bold: "Hero", text: " — dark gradient, dot-grid pattern, copper CTA" },
      { bold: "Features", text: " — 3 cards with icons and descriptions" },
      { bold: "Fields", text: " — homepage_hero group with headline, subtitle, cta_text, cta_url" },
    ],
    suggestions: ["Add a testimonials section", "Make the hero taller", "Add a stats bar below hero"]
  },
  {
    role: "user",
    content: "Make the features section feel more premium. Add subtle borders, more whitespace, and a section title."
  },
  {
    role: "assistant",
    content: "Refined the features grid — labeled section header, warmer borders, more breathing room between cards. The layout feels considered now.",
    hasChanges: true,
    suggestions: ["Add hover animations to cards", "Change to 2-column layout", "Add icons to each card"]
  },
];

export default function TektonBuilder() {
  const [msgs, setMsgs] = useState(INIT_MESSAGES);
  const [input, setInput] = useState("");
  const [page, setPage] = useState(PAGES[0]);
  const [showPages, setShowPages] = useState(false);
  const [viewport, setViewport] = useState("desktop");
  const [sidebar, setSidebar] = useState(null); // null | "tree" | "versions" | "fields"
  const [selComp, setSelComp] = useState(null);
  const [streaming, setStreaming] = useState(false);
  const endRef = useRef(null);

  useEffect(() => { endRef.current?.scrollIntoView({ behavior: "smooth" }); }, [msgs]);

  const send = (text) => {
    const val = text || input;
    if (!val.trim()) return;
    setMsgs(p => [...p, { role: "user", content: val }]);
    setInput("");
    setStreaming(true);
    setTimeout(() => {
      setMsgs(p => [...p, {
        role: "assistant",
        content: "Done — updated the preview with your changes.",
        hasChanges: true,
        suggestions: ["Adjust the spacing", "Try a different color", "Add another section"]
      }]);
      setStreaming(false);
    }, 2200);
  };

  const vw = { desktop: "100%", tablet: "768px", mobile: "375px" };

  return (
    <div style={{
      width: "100vw", height: "100vh", display: "flex", flexDirection: "column",
      background: "#1a1816", color: "#ede8e3", overflow: "hidden",
      fontFamily: "'Outfit', sans-serif", position: "relative"
    }}>
      <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,700;12..96,800&family=Outfit:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet" />

      {/* Grain */}
      <div style={{
        position: "fixed", inset: 0, pointerEvents: "none", zIndex: 999, opacity: 0.022, mixBlendMode: "overlay",
        backgroundImage: `url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E")`,
      }} />

      {/* ─── TOP BAR ─── */}
      <header style={{
        height: 48, display: "flex", alignItems: "center", justifyContent: "space-between",
        padding: "0 14px", borderBottom: "1px solid #2a2725",
        background: "#1e1c19", flexShrink: 0, zIndex: 20
      }}>
        {/* Left: logo + page */}
        <div style={{ display: "flex", alignItems: "center", gap: 14 }}>
          <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <rect x="2" y="6" width="20" height="2.2" rx="1" fill="#c97d3c"/>
              <rect x="5" y="10.5" width="14" height="2.2" rx="1" fill="#c97d3c" opacity="0.55"/>
              <rect x="8" y="15" width="8" height="2.2" rx="1" fill="#c97d3c" opacity="0.3"/>
            </svg>
            <span style={{ fontFamily: "'Bricolage Grotesque'", fontSize: 15, fontWeight: 700, letterSpacing: -0.5 }}>tekton</span>
          </div>
          <div style={{ width: 1, height: 16, background: "#2a2725" }} />

          {/* Page selector */}
          <div style={{ position: "relative" }}>
            <button onClick={() => setShowPages(!showPages)} style={{
              display: "flex", alignItems: "center", gap: 7, padding: "4px 8px",
              background: "transparent", border: "none", borderRadius: 5,
              color: "#ede8e3", cursor: "pointer", fontSize: 13, fontWeight: 500, fontFamily: "'Outfit'"
            }}>
              <span style={{ color: "#5c5753", fontSize: 11 }}>editing</span>
              <span>{page.label}</span>
              <span style={{ width: 5, height: 5, borderRadius: 3, background: page.status === "published" ? "#7dab6e" : "#c9a43c" }} />
              <svg width="10" height="10" viewBox="0 0 10 10" style={{ transform: showPages ? "rotate(180deg)" : "", transition: "0.2s" }}>
                <path d="M2.5 4L5 6.5L7.5 4" stroke="#5c5753" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" fill="none"/>
              </svg>
            </button>
            {showPages && <>
              <div onClick={() => setShowPages(false)} style={{ position: "fixed", inset: 0, zIndex: 29 }} />
              <div style={{
                position: "absolute", top: "calc(100% + 6px)", left: -4, width: 230, zIndex: 30,
                background: "#242220", border: "1px solid #3a3835", borderRadius: 10,
                padding: 5, boxShadow: "0 16px 48px #00000060"
              }}>
                {PAGES.map(p => (
                  <button key={p.key} onClick={() => { setPage(p); setShowPages(false); }} style={{
                    display: "flex", alignItems: "center", justifyContent: "space-between",
                    width: "100%", padding: "7px 10px", border: "none", borderRadius: 5,
                    background: p.key === page.key ? "#2e2c2930" : "transparent",
                    color: "#ede8e3", cursor: "pointer", fontSize: 13, fontFamily: "'Outfit'"
                  }}>
                    <div style={{ display: "flex", alignItems: "center", gap: 7 }}>
                      <span style={{ width: 5, height: 5, borderRadius: 3, background: p.status === "published" ? "#7dab6e" : "#c9a43c" }} />
                      <span style={{ fontWeight: p.key === page.key ? 600 : 400 }}>{p.label}</span>
                    </div>
                    <span style={{ fontSize: 10, color: "#3a3835", fontFamily: "'Fira Code'" }}>v{p.v}</span>
                  </button>
                ))}
              </div>
            </>}
          </div>
        </div>

        {/* Center: viewport + sidebar toggles */}
        <div style={{ display: "flex", alignItems: "center", gap: 12, position: "absolute", left: "50%", transform: "translateX(-50%)" }}>
          {/* Viewport */}
          <div style={{ display: "flex", gap: 1, background: "#242220", borderRadius: 6, padding: 2 }}>
            {[
              { key: "desktop", label: "⊞" },
              { key: "tablet", label: "▭" },
              { key: "mobile", label: "▯" },
            ].map(m => (
              <button key={m.key} onClick={() => setViewport(m.key)} style={{
                width: 28, height: 24, display: "flex", alignItems: "center", justifyContent: "center",
                border: "none", borderRadius: 4, cursor: "pointer", fontSize: 11,
                background: viewport === m.key ? "#2e2c29" : "transparent",
                color: viewport === m.key ? "#ede8e3" : "#5c5753", transition: "0.15s"
              }}>{m.label}</button>
            ))}
          </div>

          <div style={{ width: 1, height: 16, background: "#2a2725" }} />

          {/* Sidebar toggles */}
          <div style={{ display: "flex", gap: 1, background: "#242220", borderRadius: 6, padding: 2 }}>
            {[
              { key: "tree", label: "Tree" },
              { key: "versions", label: "History" },
              { key: "fields", label: "Fields" },
              { key: "plugins", label: "Plugins" },
            ].map(s => (
              <button key={s.key} onClick={() => setSidebar(sidebar === s.key ? null : s.key)} style={{
                padding: "4px 12px", border: "none", borderRadius: 4, cursor: "pointer",
                fontSize: 11, fontWeight: 500, fontFamily: "'Outfit'",
                background: sidebar === s.key ? "#2e2c29" : "transparent",
                color: sidebar === s.key ? "#ede8e3" : "#5c5753", transition: "0.15s"
              }}>{s.label}</button>
            ))}
          </div>
        </div>

        {/* Right: version + actions */}
        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
          <span style={{ fontSize: 10, color: "#3a3835", fontFamily: "'Fira Code'" }}>v{page.v}</span>
          <button style={{
            padding: "5px 12px", background: "transparent", border: "1px solid #2a2725",
            borderRadius: 6, color: "#8a847d", cursor: "pointer", fontSize: 12, fontFamily: "'Outfit'", fontWeight: 500
          }}>Preview</button>
          <button style={{
            padding: "5px 18px", background: "#c97d3c", border: "none", borderRadius: 6,
            color: "#1a1816", cursor: "pointer", fontSize: 12, fontWeight: 600, fontFamily: "'Outfit'"
          }}>Publish</button>
        </div>
      </header>

      {/* ─── MAIN ─── */}
      <div style={{ flex: 1, display: "flex", overflow: "hidden" }}>

        {/* ═══ LEFT: CHAT ═══ */}
        <div style={{
          width: 400, flexShrink: 0, display: "flex", flexDirection: "column",
          background: "#1a1816", borderRight: "1px solid #2a2725"
        }}>
          {/* Messages */}
          <div style={{ flex: 1, overflow: "auto", padding: "16px 16px 8px" }}>
            <div style={{ display: "flex", flexDirection: "column", gap: 20 }}>
              {msgs.map((m, i) => (
                <div key={i} style={{ display: "flex", flexDirection: "column", gap: 5 }}>
                  {/* Role label */}
                  <span style={{
                    fontSize: 10, fontWeight: 600, textTransform: "uppercase", letterSpacing: 1.2,
                    color: m.role === "user" ? "#5c5753" : "#c97d3c", paddingLeft: 2
                  }}>{m.role === "user" ? "You" : "Tekton"}</span>

                  {/* Message body */}
                  <div style={{
                    padding: "12px 14px", borderRadius: 10, fontSize: 13.5, lineHeight: 1.65,
                    ...(m.role === "user"
                      ? { background: "#242220", color: "#ede8e3", borderLeft: "2px solid #3a3835" }
                      : { background: "#1e1c19", color: "#c4bdb5", borderLeft: "2px solid #c97d3c30" }
                    )
                  }}>
                    {m.content}

                    {/* Build card (inline in chat) */}
                    {m.buildCard && (
                      <div style={{
                        marginTop: 12, padding: "12px 14px", borderRadius: 8,
                        border: "1px solid #2a2725", background: "#1a181680"
                      }}>
                        <div style={{
                          fontSize: 13, fontWeight: 600, color: "#ede8e3", marginBottom: 10,
                          display: "flex", alignItems: "center", justifyContent: "space-between"
                        }}>
                          {m.buildCard.title}
                          <span style={{ fontSize: 10, color: "#7dab6e", fontWeight: 500 }}>Complete</span>
                        </div>
                        {m.buildCard.steps.map((s, si) => (
                          <div key={si} style={{
                            display: "flex", alignItems: "center", gap: 8, padding: "3px 0"
                          }}>
                            <div style={{
                              width: 16, height: 16, borderRadius: 4,
                              background: s.done ? "#7dab6e18" : "#2a2725",
                              display: "flex", alignItems: "center", justifyContent: "center",
                              flexShrink: 0
                            }}>
                              {s.done && <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2.5 5.5L4 7L7.5 3.5" stroke="#7dab6e" strokeWidth="1.3" strokeLinecap="round" strokeLinejoin="round"/></svg>}
                            </div>
                            <span style={{
                              fontSize: 12, color: s.done ? "#8a847d" : "#5c5753",
                              textDecoration: s.done ? "none" : "none"
                            }}>{s.label}</span>
                          </div>
                        ))}
                      </div>
                    )}

                    {/* Detail list */}
                    {m.details && (
                      <div style={{ marginTop: 10, display: "flex", flexDirection: "column", gap: 4 }}>
                        {m.details.map((d, di) => (
                          <div key={di} style={{ fontSize: 13, lineHeight: 1.55 }}>
                            <span style={{ color: "#ede8e3", fontWeight: 600 }}>{d.bold}</span>
                            <span style={{ color: "#8a847d" }}>{d.text}</span>
                          </div>
                        ))}
                      </div>
                    )}

                    {/* Applied indicator */}
                    {m.hasChanges && (
                      <div style={{
                        display: "inline-flex", alignItems: "center", gap: 5,
                        marginTop: 10, padding: "4px 10px", borderRadius: 5,
                        background: "#7dab6e0c", border: "1px solid #7dab6e18"
                      }}>
                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2 5.5L4 7.5L8 3" stroke="#7dab6e" strokeWidth="1.3" strokeLinecap="round" strokeLinejoin="round"/></svg>
                        <span style={{ fontSize: 11, color: "#7dab6e", fontWeight: 500 }}>Preview updated</span>
                      </div>
                    )}
                  </div>

                  {/* Suggested follow-up actions (contextual pills) */}
                  {m.suggestions && (
                    <div style={{ display: "flex", gap: 5, flexWrap: "wrap", paddingLeft: 2, paddingTop: 2 }}>
                      {m.suggestions.map((s, si) => (
                        <button key={si} onClick={() => send(s)} style={{
                          padding: "5px 12px", borderRadius: 6, fontSize: 12,
                          background: "#242220", border: "1px solid #2a2725",
                          color: "#8a847d", cursor: "pointer", fontFamily: "'Outfit'",
                          fontWeight: 400, transition: "all 0.15s", lineHeight: 1.3
                        }}
                        onMouseEnter={e => { e.currentTarget.style.borderColor = "#c97d3c40"; e.currentTarget.style.color = "#c4bdb5"; }}
                        onMouseLeave={e => { e.currentTarget.style.borderColor = "#2a2725"; e.currentTarget.style.color = "#8a847d"; }}
                        >{s}</button>
                      ))}
                    </div>
                  )}
                </div>
              ))}

              {/* Streaming indicator */}
              {streaming && (
                <div style={{ display: "flex", flexDirection: "column", gap: 5 }}>
                  <span style={{ fontSize: 10, fontWeight: 600, textTransform: "uppercase", letterSpacing: 1.2, color: "#c97d3c", paddingLeft: 2 }}>Tekton</span>
                  <div style={{
                    padding: "14px", borderRadius: 10, background: "#1e1c19",
                    borderLeft: "2px solid #c97d3c30", display: "flex", alignItems: "center", gap: 8
                  }}>
                    <div className="tk-ember" style={{ width: 7, height: 7, borderRadius: 4, background: "#c97d3c", flexShrink: 0 }} />
                    <span style={{ fontSize: 12, color: "#5c5753" }}>Building changes...</span>
                  </div>
                </div>
              )}
              <div ref={endRef} />
            </div>
          </div>

          {/* Input area */}
          <div style={{ padding: "8px 16px 16px", borderTop: "1px solid #2a2725" }}>
            <div style={{
              display: "flex", gap: 8, alignItems: "flex-end",
              background: "#1e1c19", borderRadius: 10, border: "1px solid #2a2725",
              padding: "10px 12px", transition: "border-color 0.2s"
            }}
            onFocus={e => e.currentTarget.style.borderColor = "#3a3835"}
            onBlur={e => e.currentTarget.style.borderColor = "#2a2725"}
            >
              <textarea
                value={input} onChange={e => setInput(e.target.value)}
                onKeyDown={e => { if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); send(); }}}
                placeholder="Describe what to build or change..."
                rows={1}
                style={{
                  flex: 1, background: "transparent", border: "none", color: "#ede8e3",
                  fontSize: 13, lineHeight: 1.5, resize: "none", outline: "none",
                  fontFamily: "'Outfit'", minHeight: 20, maxHeight: 100
                }}
                onInput={e => { e.target.style.height = "20px"; e.target.style.height = e.target.scrollHeight + "px"; }}
              />
              <button onClick={() => send()} disabled={!input.trim()} style={{
                width: 30, height: 30, borderRadius: 7, border: "none", cursor: "pointer",
                background: input.trim() ? "#c97d3c" : "transparent",
                display: "flex", alignItems: "center", justifyContent: "center",
                transition: "0.2s", flexShrink: 0, opacity: input.trim() ? 1 : 0.4
              }}>
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                  <path d="M7 11V3M7 3L4 6M7 3l3 3" stroke={input.trim() ? "#1a1816" : "#5c5753"} strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
                </svg>
              </button>
            </div>
            <div style={{ display: "flex", alignItems: "center", gap: 6, marginTop: 6, paddingLeft: 2 }}>
              {["/fullstack", "/plugin", "/undo"].map(c => (
                <button key={c} onClick={() => setInput(c + " ")} style={{
                  padding: "2px 7px", background: "transparent", border: "1px solid #2a272580",
                  borderRadius: 4, color: "#3a3835", cursor: "pointer", fontSize: 10,
                  fontFamily: "'Fira Code'", transition: "0.15s"
                }}
                onMouseEnter={e => { e.currentTarget.style.color = "#5c5753"; e.currentTarget.style.borderColor = "#3a3835"; }}
                onMouseLeave={e => { e.currentTarget.style.color = "#3a3835"; e.currentTarget.style.borderColor = "#2a272580"; }}
                >{c}</button>
              ))}
              <span style={{ marginLeft: "auto", fontSize: 10, color: "#2a2725" }}>shift+enter for newline</span>
            </div>
          </div>
        </div>

        {/* ═══ CENTER: PREVIEW ═══ */}
        <div style={{
          flex: 1, display: "flex", alignItems: "stretch", justifyContent: "center",
          padding: viewport === "desktop" ? 0 : 24, overflow: "auto",
          background: "#151311",
          backgroundImage: viewport !== "desktop" ? "radial-gradient(#2a272518 1px, transparent 1px)" : "none",
          backgroundSize: "20px 20px", transition: "padding 0.3s"
        }}>
          <div style={{
            width: vw[viewport], maxWidth: "100%",
            height: viewport === "desktop" ? "100%" : "auto",
            minHeight: viewport !== "desktop" ? 600 : undefined,
            background: "#fff", overflow: "auto",
            borderRadius: viewport === "desktop" ? 0 : 8,
            boxShadow: viewport !== "desktop" ? "0 8px 60px #00000050" : "none",
            transition: "all 0.3s"
          }}>
            {/* ── MOCK RENDERED PAGE ── */}
            <div style={{ fontFamily: "'Outfit', sans-serif", color: "#1a1a1a" }}>
              {/* Nav */}
              <div style={{
                display: "flex", alignItems: "center", justifyContent: "space-between",
                padding: "16px 40px", borderBottom: "1px solid #eee"
              }}>
                <div style={{ fontFamily: "'Bricolage Grotesque'", fontWeight: 800, fontSize: 17, letterSpacing: -0.5 }}>CloudSync</div>
                <div style={{ display: "flex", gap: 24, fontSize: 14, color: "#666", alignItems: "center" }}>
                  <span>Features</span><span>Pricing</span><span>Docs</span>
                  <span style={{ color: "#1a1816", fontWeight: 600, padding: "5px 16px", borderRadius: 6, border: "1px solid #ddd" }}>Log In</span>
                </div>
              </div>

              {/* Hero */}
              <div style={{
                background: "#1a1816", padding: viewport === "mobile" ? "56px 24px" : "96px 48px",
                position: "relative", overflow: "hidden"
              }}>
                <div style={{ position: "absolute", inset: 0, opacity: 0.06, backgroundImage: "radial-gradient(#c97d3c 1px, transparent 1px)", backgroundSize: "32px 32px" }} />
                <div style={{ position: "absolute", right: "-10%", top: "-40%", width: "55%", height: "180%", background: "radial-gradient(ellipse, #c97d3c0c 0%, transparent 65%)" }} />
                <div style={{ position: "relative", maxWidth: 580 }}>
                  <div style={{
                    fontFamily: "'Bricolage Grotesque'", fontWeight: 800,
                    fontSize: viewport === "mobile" ? 30 : 50, color: "#ede8e3",
                    lineHeight: 1.06, letterSpacing: -2
                  }}>Sync everything.<br/>Ship faster.</div>
                  <div style={{
                    fontSize: viewport === "mobile" ? 14 : 16, color: "#8a847d",
                    marginTop: 18, lineHeight: 1.7, maxWidth: 420
                  }}>Real-time file syncing for developer teams. No merge conflicts. No "works on my machine." Just flow.</div>
                  <div style={{ display: "flex", gap: 10, marginTop: 28 }}>
                    <button style={{ padding: "12px 26px", borderRadius: 7, border: "none", background: "#c97d3c", color: "#1a1816", fontSize: 14, fontWeight: 600, cursor: "pointer", fontFamily: "'Outfit'" }}>Start Free Trial</button>
                    <button style={{ padding: "12px 26px", borderRadius: 7, border: "1px solid #3a3835", background: "transparent", color: "#8a847d", fontSize: 14, fontWeight: 500, cursor: "pointer", fontFamily: "'Outfit'" }}>View Demo</button>
                  </div>
                </div>
              </div>

              {/* Features */}
              <div style={{ padding: viewport === "mobile" ? "48px 24px" : "76px 48px" }}>
                <div style={{ marginBottom: 44 }}>
                  <div style={{ fontSize: 11, fontWeight: 700, textTransform: "uppercase", letterSpacing: 3, color: "#c97d3c" }}>Features</div>
                  <div style={{ fontFamily: "'Bricolage Grotesque'", fontSize: viewport === "mobile" ? 24 : 30, fontWeight: 700, color: "#1a1816", marginTop: 8, letterSpacing: -0.8 }}>Built for how teams work</div>
                  <div style={{ width: 36, height: 2, background: "#c97d3c", marginTop: 14, borderRadius: 1 }} />
                </div>
                <div style={{
                  display: "grid", gridTemplateColumns: viewport === "mobile" ? "1fr" : "1fr 1fr 1fr", gap: 14
                }}>
                  {[
                    { title: "Real-time Sync", desc: "Changes propagate instantly across your entire team. Every save, every branch." },
                    { title: "Encrypted by Default", desc: "End-to-end encryption on every file. Your code never touches our servers in plaintext." },
                    { title: "One-Click Deploy", desc: "Push to staging or production with a single command. Rollbacks just as fast." }
                  ].map((f, i) => (
                    <div key={i} style={{ padding: 28, borderRadius: 10, border: "1px solid #e8e4df" }}>
                      <div style={{ width: 32, height: 32, borderRadius: 7, background: "#c97d3c0c", marginBottom: 16, display: "flex", alignItems: "center", justifyContent: "center" }}>
                        <div style={{ width: 7, height: 7, borderRadius: 4, background: "#c97d3c" }} />
                      </div>
                      <div style={{ fontFamily: "'Bricolage Grotesque'", fontSize: 17, fontWeight: 700, marginBottom: 6 }}>{f.title}</div>
                      <div style={{ fontSize: 13.5, color: "#777", lineHeight: 1.65 }}>{f.desc}</div>
                    </div>
                  ))}
                </div>
              </div>

              {/* CTA Banner */}
              <div style={{
                margin: "0 48px 48px", padding: "48px 40px", borderRadius: 12,
                background: "#1a1816", position: "relative", overflow: "hidden"
              }}>
                <div style={{ position: "absolute", inset: 0, opacity: 0.04, backgroundImage: "radial-gradient(#c97d3c 1px, transparent 1px)", backgroundSize: "24px 24px" }} />
                <div style={{ position: "relative", display: "flex", alignItems: "center", justifyContent: "space-between" }}>
                  <div>
                    <div style={{ fontFamily: "'Bricolage Grotesque'", fontSize: 24, fontWeight: 700, color: "#ede8e3", letterSpacing: -0.5 }}>Ready to sync?</div>
                    <div style={{ fontSize: 14, color: "#8a847d", marginTop: 6 }}>Get started in under 2 minutes. Free for teams up to 5.</div>
                  </div>
                  <button style={{ padding: "12px 28px", borderRadius: 7, border: "none", background: "#c97d3c", color: "#1a1816", fontSize: 14, fontWeight: 600, cursor: "pointer", fontFamily: "'Outfit'", flexShrink: 0 }}>Start Free Trial</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* ═══ RIGHT SIDEBAR (on demand) ═══ */}
        {sidebar && (
          <div style={{
            width: 260, flexShrink: 0, background: "#1e1c19",
            borderLeft: "1px solid #2a2725", display: "flex", flexDirection: "column",
            overflow: "hidden"
          }}>
            <div style={{
              padding: "10px 14px", borderBottom: "1px solid #2a2725",
              display: "flex", alignItems: "center", justifyContent: "space-between"
            }}>
              <span style={{ fontSize: 10, fontWeight: 600, textTransform: "uppercase", letterSpacing: 1.5, color: "#5c5753" }}>
                {sidebar === "tree" ? "Component Tree" : sidebar === "versions" ? "Version History" : sidebar === "fields" ? "Field Groups" : "Generated Plugins"}
              </span>
              <button onClick={() => setSidebar(null)} style={{
                background: "transparent", border: "none", color: "#3a3835",
                cursor: "pointer", fontSize: 15, lineHeight: 1, padding: 2
              }}>×</button>
            </div>

            <div style={{ flex: 1, overflow: "auto", padding: "8px 8px" }}>
              {/* Tree */}
              {sidebar === "tree" && (
                <div style={{ display: "flex", flexDirection: "column", gap: 1 }}>
                  {TREE.map(n => (
                    <button key={n.id} onClick={() => setSelComp(n.id)} style={{
                      display: "flex", alignItems: "center", gap: 7,
                      padding: "4px 6px", paddingLeft: 6 + n.depth * 16,
                      background: selComp === n.id ? "#c97d3c0c" : "transparent",
                      border: selComp === n.id ? "1px solid #c97d3c18" : "1px solid transparent",
                      borderRadius: 5, cursor: "pointer", width: "100%", textAlign: "left"
                    }}>
                      <span style={{
                        width: 16, height: 16, borderRadius: 3, flexShrink: 0, fontSize: 8,
                        fontWeight: 700, fontFamily: "'Fira Code'",
                        display: "flex", alignItems: "center", justifyContent: "center",
                        background: (TYPE_MAP[n.type]?.hue || "#5c5753") + "15",
                        color: TYPE_MAP[n.type]?.hue || "#5c5753"
                      }}>{TYPE_MAP[n.type]?.letter || "?"}</span>
                      <span style={{
                        fontSize: 11.5, color: selComp === n.id ? "#ede8e3" : "#8a847d",
                        fontWeight: selComp === n.id ? 600 : 400, whiteSpace: "nowrap",
                        overflow: "hidden", textOverflow: "ellipsis"
                      }}>{n.label}</span>
                      <span style={{
                        marginLeft: "auto", fontSize: 9, color: "#2a2725",
                        fontFamily: "'Fira Code'", flexShrink: 0
                      }}>{n.type}</span>
                    </button>
                  ))}
                </div>
              )}

              {/* Versions */}
              {sidebar === "versions" && (
                <div style={{ display: "flex", flexDirection: "column", gap: 2 }}>
                  {VERSIONS.map(v => (
                    <div key={v.v} style={{
                      padding: "8px 8px", borderRadius: 5,
                      background: v.current ? "#c97d3c08" : "transparent"
                    }}>
                      <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 3 }}>
                        <span style={{
                          fontSize: 11, fontWeight: 600, fontFamily: "'Fira Code'",
                          color: v.current ? "#c97d3c" : "#3a3835"
                        }}>v{v.v}</span>
                        {v.current && <span style={{ fontSize: 9, color: "#7dab6e", fontWeight: 600 }}>CURRENT</span>}
                        <span style={{ marginLeft: "auto", fontSize: 10, color: "#2a2725" }}>{v.time}</span>
                      </div>
                      <div style={{ fontSize: 12, color: "#8a847d", lineHeight: 1.4 }}>{v.prompt}</div>
                      {!v.current && (
                        <button style={{
                          marginTop: 5, padding: "3px 10px", background: "transparent",
                          border: "1px solid #2a2725", borderRadius: 4, color: "#5c5753",
                          cursor: "pointer", fontSize: 10, fontFamily: "'Outfit'"
                        }}>Restore</button>
                      )}
                    </div>
                  ))}
                </div>
              )}

              {/* Fields */}
              {sidebar === "fields" && (
                <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                  {FIELDS.map(g => (
                    <div key={g.slug} style={{
                      padding: 10, borderRadius: 7, border: "1px solid #2a2725", background: "#242220"
                    }}>
                      <div style={{ fontSize: 12, fontWeight: 600, color: "#ede8e3", marginBottom: 2 }}>{g.title}</div>
                      <div style={{ fontSize: 10, color: "#3a3835", fontFamily: "'Fira Code'", marginBottom: 8 }}>{g.slug}</div>
                      {g.fields.map(f => (
                        <div key={f} style={{
                          fontSize: 11, color: "#5c5753", padding: "2px 0",
                          fontFamily: "'Fira Code'", display: "flex", alignItems: "center", gap: 6
                        }}>
                          <span style={{ width: 3, height: 3, borderRadius: 2, background: "#c97d3c40", flexShrink: 0 }} />
                          {f}
                        </div>
                      ))}
                    </div>
                  ))}
                  <button style={{
                    padding: 10, borderRadius: 7, border: "1px dashed #2a2725",
                    background: "transparent", color: "#3a3835", cursor: "pointer",
                    fontSize: 11, fontFamily: "'Outfit'", textAlign: "center"
                  }}>+ New field group</button>
                </div>
              )}

              {/* Plugins */}
              {sidebar === "plugins" && (
                <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                  {[
                    { name: "Contact Form", slug: "tekton-contact-form", on: true, desc: "Email notifications on submit" },
                    { name: "Newsletter Signup", slug: "tekton-newsletter", on: true, desc: "Mailchimp integration" },
                    { name: "Appointment Booking", slug: "tekton-booking", on: false, desc: "Calendar-based booking" },
                  ].map(p => (
                    <div key={p.slug} style={{
                      padding: 10, borderRadius: 7, border: "1px solid #2a2725", background: "#242220"
                    }}>
                      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 4 }}>
                        <span style={{ fontSize: 12, fontWeight: 600, color: "#ede8e3" }}>{p.name}</span>
                        <div style={{
                          width: 28, height: 16, borderRadius: 8, padding: 2, cursor: "pointer",
                          background: p.on ? "#7dab6e" : "#2a2725", transition: "0.2s",
                          display: "flex", alignItems: "center",
                          justifyContent: p.on ? "flex-end" : "flex-start"
                        }}>
                          <div style={{ width: 12, height: 12, borderRadius: 6, background: p.on ? "#fff" : "#5c5753" }} />
                        </div>
                      </div>
                      <div style={{ fontSize: 10, color: "#3a3835", fontFamily: "'Fira Code'", marginBottom: 4 }}>{p.slug}</div>
                      <div style={{ fontSize: 11, color: "#5c5753", lineHeight: 1.4 }}>{p.desc}</div>
                    </div>
                  ))}
                  <button style={{
                    padding: 10, borderRadius: 7, border: "1px dashed #2a2725",
                    background: "transparent", color: "#3a3835", cursor: "pointer",
                    fontSize: 11, fontFamily: "'Outfit'", textAlign: "center"
                  }}>Generate with /plugin</button>
                </div>
              )}
            </div>
          </div>
        )}
      </div>

      <style>{`
        * { box-sizing: border-box; margin: 0; }
        @keyframes ember { 0%,100% { opacity:.35; transform:scale(.85) } 50% { opacity:1; transform:scale(1.15) } }
        .tk-ember { animation: ember 1.6s ease-in-out infinite; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #2a2725; border-radius: 3px; }
        textarea::placeholder { color: #3a3835; }
      `}</style>
    </div>
  );
}