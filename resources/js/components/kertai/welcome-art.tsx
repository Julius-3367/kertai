import type { SVGAttributes } from 'react';

type Art = SVGAttributes<SVGElement>;

/** Hills of coffee and sugarcane at sunrise, with the members together in front. */
export function HeroArt(props: Art) {
    return (
        <svg
            viewBox="0 0 560 440"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            {...props}
        >
            <defs>
                <linearGradient id="hero-sky" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#fef3c7" />
                    <stop offset="0.55" stopColor="#ecfdf5" />
                    <stop offset="1" stopColor="#d1fae5" />
                </linearGradient>
                <radialGradient id="hero-sun" cx="0.5" cy="0.5" r="0.5">
                    <stop offset="0" stopColor="#fcd34d" />
                    <stop offset="1" stopColor="#f59e0b" />
                </radialGradient>
                <clipPath id="hero-frame">
                    <rect width="560" height="440" rx="40" />
                </clipPath>
            </defs>

            <g clipPath="url(#hero-frame)">
                <rect width="560" height="440" fill="url(#hero-sky)" />

                {/* sun */}
                <circle
                    cx="408"
                    cy="128"
                    r="86"
                    fill="#fde68a"
                    opacity="0.45"
                />
                <circle cx="408" cy="128" r="50" fill="url(#hero-sun)" />

                {/* clouds */}
                <g
                    className="motion-safe:animate-drift"
                    fill="#ffffff"
                    opacity="0.9"
                >
                    <rect x="70" y="70" width="110" height="26" rx="13" />
                    <rect x="98" y="54" width="54" height="30" rx="15" />
                </g>
                <g
                    className="motion-safe:animate-drift-slow"
                    fill="#ffffff"
                    opacity="0.75"
                >
                    <rect x="250" y="118" width="84" height="20" rx="10" />
                    <rect x="268" y="106" width="40" height="22" rx="11" />
                </g>

                {/* far hills */}
                <path
                    d="M0 250C70 205 140 190 220 212C300 234 360 190 440 180C500 172 540 190 560 200V440H0Z"
                    fill="#a7f3d0"
                />
                {/* coffee rows on the middle hill */}
                <path
                    d="M0 292C90 248 190 246 280 268C370 290 450 250 560 238V440H0Z"
                    fill="#6ee7b7"
                />
                <g fill="#047857">
                    {[40, 80, 120, 160, 200, 240].map((x, i) => (
                        <g
                            key={x}
                            transform={`translate(${x} ${282 - i * 3 + (i > 3 ? (i - 3) * 6 : 0)})`}
                        >
                            <circle cx="0" cy="0" r="11" />
                            <circle cx="12" cy="3" r="9" />
                            <circle cx="-10" cy="4" r="8" />
                            <circle cx="2" cy="-2" r="2.2" fill="#dc2626" />
                            <circle cx="9" cy="2" r="2" fill="#b91c1c" />
                        </g>
                    ))}
                </g>

                {/* sugarcane on the right */}
                <g stroke="#4d7c0f" strokeWidth="5" strokeLinecap="round">
                    {[430, 452, 474, 496, 518].map((x, i) => (
                        <g key={x}>
                            <path d={`M${x} 300V${190 + (i % 2) * 18}`} />
                            <path
                                d={`M${x} ${226 + (i % 2) * 14}c14-6 24-16 30-30`}
                                stroke="#84cc16"
                                strokeWidth="4"
                                fill="none"
                            />
                            <path
                                d={`M${x} ${252 + (i % 2) * 10}c-14-6-22-16-28-30`}
                                stroke="#65a30d"
                                strokeWidth="4"
                                fill="none"
                            />
                        </g>
                    ))}
                </g>

                {/* near hill */}
                <path
                    d="M0 344C110 310 200 318 290 336C380 354 470 322 560 312V440H0Z"
                    fill="#10b981"
                />
                <path
                    d="M0 392C120 366 230 372 330 384C420 395 500 380 560 372V440H0Z"
                    fill="#047857"
                />

                {/* the members, side by side */}
                <g>
                    {[
                        { x: 150, body: '#f59e0b', head: '#7c2d12', h: 0 },
                        { x: 210, body: '#e11d48', head: '#451a03', h: -8 },
                        { x: 270, body: '#0ea5e9', head: '#78350f', h: -12 },
                        { x: 330, body: '#8b5cf6', head: '#451a03', h: -8 },
                        { x: 390, body: '#facc15', head: '#7c2d12', h: 0 },
                    ].map((p) => (
                        <g key={p.x} transform={`translate(${p.x} ${p.h})`}>
                            <path
                                d="M-26 404c0-30 12-50 26-50s26 20 26 50z"
                                fill={p.body}
                            />
                            <circle cx="0" cy="334" r="17" fill={p.head} />
                        </g>
                    ))}
                    {/* joined hands */}
                    <path
                        d="M172 378c14 6 24 4 34-2M232 372c12 6 26 6 36 0M292 372c12 6 26 6 36 0M352 376c12 6 22 6 34 2"
                        stroke="#451a03"
                        strokeWidth="6"
                        strokeLinecap="round"
                    />
                </g>

                {/* coins rising from the group */}
                <g className="motion-safe:animate-float">
                    <circle
                        cx="214"
                        cy="270"
                        r="15"
                        fill="#fbbf24"
                        stroke="#b45309"
                        strokeWidth="3"
                    />
                    <path
                        d="M209 270h10M214 265v10"
                        stroke="#b45309"
                        strokeWidth="3"
                        strokeLinecap="round"
                    />
                </g>
                <g className="motion-safe:animate-float-slow">
                    <circle
                        cx="318"
                        cy="246"
                        r="12"
                        fill="#fbbf24"
                        stroke="#b45309"
                        strokeWidth="3"
                    />
                    <circle
                        cx="318"
                        cy="246"
                        r="5"
                        stroke="#b45309"
                        strokeWidth="2"
                    />
                </g>
                <g className="motion-safe:animate-float">
                    <circle
                        cx="268"
                        cy="214"
                        r="9"
                        fill="#fde68a"
                        stroke="#b45309"
                        strokeWidth="2.5"
                    />
                </g>
            </g>
        </svg>
    );
}

/** A savings jar filling with coins. */
export function SavingsArt(props: Art) {
    return (
        <svg viewBox="0 0 64 64" fill="none" aria-hidden="true" {...props}>
            <circle cx="32" cy="32" r="30" fill="#d1fae5" />
            <rect
                x="19"
                y="20"
                width="26"
                height="32"
                rx="8"
                fill="#ffffff"
                stroke="#047857"
                strokeWidth="2.5"
            />
            <rect x="22" y="15" width="20" height="7" rx="3" fill="#047857" />
            <circle
                cx="28"
                cy="44"
                r="5"
                fill="#fbbf24"
                stroke="#b45309"
                strokeWidth="1.5"
            />
            <circle
                cx="37"
                cy="41"
                r="5"
                fill="#fcd34d"
                stroke="#b45309"
                strokeWidth="1.5"
            />
            <circle
                cx="32"
                cy="34"
                r="5"
                fill="#fbbf24"
                stroke="#b45309"
                strokeWidth="1.5"
            />
            <circle
                cx="46"
                cy="10"
                r="5"
                fill="#fbbf24"
                stroke="#b45309"
                strokeWidth="1.5"
                className="motion-safe:animate-float"
            />
        </svg>
    );
}

/** Members taking turns around a circle. */
export function RoundArt(props: Art) {
    return (
        <svg viewBox="0 0 64 64" fill="none" aria-hidden="true" {...props}>
            <circle cx="32" cy="32" r="30" fill="#fef3c7" />
            <circle
                cx="32"
                cy="32"
                r="15"
                stroke="#d97706"
                strokeWidth="2.5"
                strokeDasharray="6 5"
            />
            <path
                d="M44 22l3 4-5 1"
                stroke="#d97706"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M20 42l-3-4 5-1"
                stroke="#d97706"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle cx="32" cy="15" r="5" fill="#e11d48" />
            <circle cx="49" cy="32" r="5" fill="#0ea5e9" />
            <circle cx="32" cy="49" r="5" fill="#8b5cf6" />
            <circle cx="15" cy="32" r="5" fill="#047857" />
            <circle
                cx="32"
                cy="32"
                r="5"
                fill="#fbbf24"
                stroke="#b45309"
                strokeWidth="1.5"
            />
        </svg>
    );
}

/** Two hands holding a heart. */
export function WelfareArt(props: Art) {
    return (
        <svg viewBox="0 0 64 64" fill="none" aria-hidden="true" {...props}>
            <circle cx="32" cy="32" r="30" fill="#ffe4e6" />
            <path
                d="M32 34c-6-5-11-8-11-13a5.5 5.5 0 0 1 11-1 5.5 5.5 0 0 1 11 1c0 5-5 8-11 13z"
                fill="#e11d48"
            />
            <path
                d="M10 38c6 0 9 3 13 6h9c2 0 3 2 1 3l-8 1M54 38c-6 0-9 3-13 6h-9"
                stroke="#9f1239"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M10 50h8l6 3h16l6-3h8"
                stroke="#9f1239"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/** A coffee branch with ripe cherries. */
export function ProjectsArt(props: Art) {
    return (
        <svg viewBox="0 0 64 64" fill="none" aria-hidden="true" {...props}>
            <circle cx="32" cy="32" r="30" fill="#ecfccb" />
            <path
                d="M14 50C24 40 34 30 50 14"
                stroke="#3f6212"
                strokeWidth="2.5"
                strokeLinecap="round"
            />
            <path d="M26 38c-8-1-12-6-12-12 7 0 11 5 12 12z" fill="#65a30d" />
            <path d="M38 26c0-8 4-12 10-13 1 7-3 11-10 13z" fill="#4d7c0f" />
            <path d="M32 32c6 4 7 10 5 15-6-3-7-9-5-15z" fill="#84cc16" />
            <circle cx="22" cy="44" r="3.5" fill="#dc2626" />
            <circle cx="28" cy="42" r="3.5" fill="#b91c1c" />
            <circle cx="40" cy="30" r="3" fill="#dc2626" />
        </svg>
    );
}

/** Tents and chairs, for the fundraiser cards. */
export function FundraiserArt(props: Art) {
    return (
        <svg viewBox="0 0 120 64" fill="none" aria-hidden="true" {...props}>
            <path
                d="M8 58h104"
                stroke="#a7f3d0"
                strokeWidth="3"
                strokeLinecap="round"
            />
            <path
                d="M20 58L48 22l28 36"
                fill="#ffffff"
                stroke="#047857"
                strokeWidth="2.5"
                strokeLinejoin="round"
            />
            <path
                d="M48 22v36M38 58l10-14 10 14"
                stroke="#047857"
                strokeWidth="2.5"
                strokeLinejoin="round"
            />
            <path d="M48 22l-3-8h6z" fill="#e11d48" />
            <path
                d="M84 40h16v-12M84 40v18M100 40v18M84 48h16"
                stroke="#b45309"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/** Soft wave used to separate sections. */
export function Wave(props: Art) {
    return (
        <svg
            viewBox="0 0 1440 80"
            preserveAspectRatio="none"
            aria-hidden="true"
            {...props}
        >
            <path
                d="M0 40C160 70 320 80 480 64C640 48 800 8 960 6C1120 4 1280 40 1440 52V80H0Z"
                fill="currentColor"
            />
        </svg>
    );
}
