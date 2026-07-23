    <style>
        [x-cloak]{display:none!important}
        [id]{scroll-margin-top:6rem}

        /* ============================================================
           PoisaPay landing — LIGHT theme, matched to the user dashboard
           (theme-minimal): blue #2563EB primary, slate neutrals, white
           cards with hairline borders + soft shadows, Inter typography.
           Scoped to .poisa-landing so it never leaks into the app/admin.
           ============================================================ */
        .poisa-landing{
            --bg:#f8fafc;         /* slate-50 canvas (dashboard bg) */
            --surface:#ffffff;
            --brand:#2563eb;      /* blue-600 primary */
            --brand-600:#1d4ed8;
            --brand-50:#eff6ff;
            --cyan:#0ea5e9;
            --indigo:#4f46e5;
            --warn:#f59e0b;
            --up:#10b981;
            --down:#f43f5e;
            --stroke:#e2e8f0;     /* slate-200 hairline */
            --stroke-2:#cbd5e1;   /* slate-300 */
            --txt:#0f172a;        /* slate-900 */
            --muted:#475569;      /* slate-600 */
            --muted-2:#64748b;    /* slate-500 */
            --shadow-card:0 1px 2px 0 rgb(15 23 42 / .04), 0 1px 3px 0 rgb(15 23 42 / .05);
            --shadow-pop:0 18px 40px -18px rgb(37 99 235 / .28);
            font-family:'Inter',ui-sans-serif,system-ui,sans-serif;
            background:var(--bg);
            color:var(--muted);
            letter-spacing:-.011em;
            font-feature-settings:'cv11','ss01';
            -webkit-font-smoothing:antialiased;
            -moz-osx-font-smoothing:grayscale;
        }
        .poisa-landing ::selection{background:rgba(37,99,235,.16);color:#0f172a}
        .poisa-landing h1,.poisa-landing h2,.poisa-landing h3{color:var(--txt);letter-spacing:-.03em}
        .poisa-landing .tabular{font-variant-numeric:tabular-nums}

        /* ---- Ambient background (soft, calm — Mercury/Linear feel) ---- */
        .pp-mesh{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
        .pp-mesh::before,.pp-mesh::after{content:"";position:absolute;border-radius:50%;filter:blur(90px);opacity:.7}
        .pp-mesh::before{width:50rem;height:50rem;top:-20rem;left:-12rem;background:radial-gradient(circle,rgba(37,99,235,.14),transparent 62%);animation:ppDrift 28s ease-in-out infinite alternate}
        .pp-mesh::after{width:44rem;height:44rem;top:6rem;right:-14rem;background:radial-gradient(circle,rgba(14,165,233,.12),transparent 62%);animation:ppDrift 32s ease-in-out infinite alternate-reverse}
        .pp-grid-overlay{position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.6;
            background-image:linear-gradient(rgba(15,23,42,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(15,23,42,.035) 1px,transparent 1px);
            background-size:64px 64px;
            mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,#000 30%,transparent 100%);
            -webkit-mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,#000 30%,transparent 100%)}

        /* ---- Surfaces (white cards + light frosted glass) ---- */
        .glass{background:rgba(255,255,255,.72);border:1px solid var(--stroke);backdrop-filter:blur(12px) saturate(140%);-webkit-backdrop-filter:blur(12px) saturate(140%);box-shadow:var(--shadow-card)}
        .glass-2{background:rgba(255,255,255,.85);border:1px solid var(--stroke);backdrop-filter:blur(16px) saturate(150%);-webkit-backdrop-filter:blur(16px) saturate(150%)}
        .glass-card{background:var(--surface);border:1px solid var(--stroke);border-radius:1.25rem;box-shadow:var(--shadow-card)}
        .glass-hover{transition:transform .35s cubic-bezier(.2,.7,.2,1),border-color .35s,box-shadow .35s}
        .glass-hover:hover{transform:translateY(-6px);border-color:#bfdbfe;box-shadow:var(--shadow-pop)}

        /* ---- Gradient text + accents ---- */
        .grad-text{background:linear-gradient(100deg,var(--brand) 0%,var(--cyan) 55%,var(--indigo) 100%);-webkit-background-clip:text;background-clip:text;color:transparent}
        .grad-text-anim{background:linear-gradient(100deg,var(--brand),var(--cyan),var(--indigo),var(--brand));background-size:280% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:ppPan 8s linear infinite}
        .ring-chip{border:1px solid var(--stroke);background:#fff;box-shadow:var(--shadow-card)}

        /* ---- Buttons ---- */
        .pp-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border-radius:.85rem;font-weight:600;line-height:1;transition:transform .2s,box-shadow .3s,background .2s,border-color .2s;white-space:nowrap}
        .pp-btn:active{transform:translateY(1px) scale(.99)}
        .pp-btn-primary{background:linear-gradient(120deg,var(--brand),var(--brand-600));color:#fff;box-shadow:0 10px 24px -10px rgba(37,99,235,.6),inset 0 1px 0 rgba(255,255,255,.2)}
        .pp-btn-primary:hover{box-shadow:0 16px 34px -10px rgba(37,99,235,.7);transform:translateY(-2px)}
        .pp-btn-ghost{background:#fff;color:var(--txt);border:1px solid var(--stroke-2);box-shadow:var(--shadow-card)}
        .pp-btn-ghost:hover{background:#f8fafc;border-color:var(--stroke-2);transform:translateY(-2px)}
        .pp-btn-sm{padding:.55rem .95rem;font-size:.85rem}
        .pp-btn-md{padding:.7rem 1.15rem;font-size:.92rem}
        .pp-btn-lg{padding:.95rem 1.6rem;font-size:1rem}
        .poisa-landing a:focus-visible,.poisa-landing button:focus-visible,.poisa-landing summary:focus-visible{outline:2px solid var(--brand);outline-offset:3px;border-radius:.6rem}

        /* ---- 3D card + finishes (physical card colours, sit on light bg) ---- */
        .pp-card3d{transition:transform .2s ease-out;will-change:transform;transform-style:preserve-3d}
        .card-emerald{background:radial-gradient(120% 120% at 0% 0%,#0f3b32 0%,#0a2a26 40%,#06201d 100%)}
        .card-obsidian{background:radial-gradient(120% 120% at 20% 0%,#2a2f3a 0%,#141821 45%,#080a0f 100%)}
        .card-titanium{background:linear-gradient(150deg,#c7ccd6 0%,#8b93a3 42%,#5b6273 100%)}
        .card-titanium .text-white,.card-titanium .font-mono,.card-titanium p,.card-titanium span{color:#101521 !important}
        .card-aurora{background:linear-gradient(135deg,#1e40af 0%,#2563eb 42%,#0ea5e9 100%)}
        .card-chip{background:linear-gradient(135deg,#f6d488,#c69a4b);box-shadow:inset 0 0 0 1px rgba(0,0,0,.15);position:relative}
        .card-chip::after{content:"";position:absolute;inset:22% 30%;border:1px solid rgba(0,0,0,.25);border-radius:2px}
        .card-sheen{background:linear-gradient(115deg,transparent 30%,rgba(255,255,255,.28) 46%,rgba(255,255,255,.05) 54%,transparent 70%);background-size:250% 250%;animation:ppSheen 6s ease-in-out infinite}

        /* ---- Floating + reveal ---- */
        .pp-float{animation:ppFloat 7s ease-in-out infinite}
        .pp-float-slow{animation:ppFloat 10s ease-in-out infinite}
        .reveal{opacity:0;transform:translateY(26px);transition:opacity .7s cubic-bezier(.2,.7,.2,1),transform .7s cubic-bezier(.2,.7,.2,1)}
        .reveal.in{opacity:1;transform:none}
        .reveal[data-d="1"]{transition-delay:.08s}.reveal[data-d="2"]{transition-delay:.16s}
        .reveal[data-d="3"]{transition-delay:.24s}.reveal[data-d="4"]{transition-delay:.32s}

        /* ---- Marquee ---- */
        .pp-marquee{display:flex;gap:3.5rem;width:max-content;animation:ppMarquee 26s linear infinite}
        .pp-marquee-mask{mask-image:linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent);-webkit-mask-image:linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent)}

        /* ---- Keyframes ---- */
        @keyframes ppFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-14px)}}
        @keyframes ppDrift{0%{transform:translate(0,0) scale(1)}100%{transform:translate(4rem,3rem) scale(1.12)}}
        @keyframes ppPan{0%{background-position:0% 50%}100%{background-position:280% 50%}}
        @keyframes ppSheen{0%,100%{background-position:120% 0}50%{background-position:-20% 0}}
        @keyframes ppMarquee{to{transform:translateX(-50%)}}
        @keyframes ppPulse{0%{box-shadow:0 0 0 0 rgba(37,99,235,.4)}70%{box-shadow:0 0 0 10px rgba(37,99,235,0)}100%{box-shadow:0 0 0 0 rgba(37,99,235,0)}}
        .pp-pulse{animation:ppPulse 2.4s infinite}

        @media (prefers-reduced-motion: reduce){
            .pp-float,.pp-float-slow,.card-sheen,.pp-mesh::before,.pp-mesh::after,.pp-marquee,.grad-text-anim,.pp-pulse{animation:none!important}
            .reveal{opacity:1;transform:none}
        }
    </style>
