<svg viewBox="0 0 128 128" xmlns="http://www.w3.org/2000/svg" {{ $attributes }}>
    <defs>
        <linearGradient id="lumi-core" x1="24" y1="14" x2="86" y2="74" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="#FDE68A"/>
            <stop offset="0.55" stop-color="#F59E0B"/>
            <stop offset="1" stop-color="#EA580C"/>
        </linearGradient>
        <linearGradient id="lumi-ray" x1="64" y1="8" x2="64" y2="78" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="#FCD34D"/>
            <stop offset="1" stop-color="#F97316"/>
        </linearGradient>
    </defs>

    <g transform="translate(64 42)">
        <circle cx="0" cy="0" r="24" fill="url(#lumi-core)"/>
        <circle cx="0" cy="0" r="30" fill="none" stroke="#FDBA74" stroke-width="2" opacity="0.7"/>

        <g stroke="url(#lumi-ray)" stroke-width="3" stroke-linecap="round">
            <line x1="0" y1="-42" x2="0" y2="-34"/>
            <line x1="29.7" y1="-29.7" x2="24.1" y2="-24.1"/>
            <line x1="42" y1="0" x2="34" y2="0"/>
            <line x1="29.7" y1="29.7" x2="24.1" y2="24.1"/>
            <line x1="0" y1="42" x2="0" y2="34"/>
            <line x1="-29.7" y1="29.7" x2="-24.1" y2="24.1"/>
            <line x1="-42" y1="0" x2="-34" y2="0"/>
            <line x1="-29.7" y1="-29.7" x2="-24.1" y2="-24.1"/>
        </g>

        <path d="M0 -13L12 13H6.5L3 6H-3L-6.5 13H-12L0 -13ZM0 0L-1.4 3.2H1.4L0 0Z" fill="#0F172A"/>
    </g>

    <text class="lumi-wordmark" x="64" y="112" text-anchor="middle" font-size="18" font-weight="700" letter-spacing="0.6" fill="#0F172A">Lumi.A</text>
</svg>
