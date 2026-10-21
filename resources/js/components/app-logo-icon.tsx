import type { SVGAttributes } from 'react';

/** A coffee seedling: two leaves on a stem. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path d="M12 21v-9" />
            <path d="M12 12c0-4 2.5-7 7-7 0 4.5-3 7-7 7z" />
            <path d="M12 15c0-3.5-2.2-6-6-6 0 3.8 2.5 6 6 6z" />
            <path d="M8 21h8" />
        </svg>
    );
}
