import { useEffect, useRef, useState, type ReactNode } from 'react';
import { formatKes } from '@/lib/format';
import { cn } from '@/lib/utils';

// Plain SVG on purpose: no chart library to download on slow mobile data.
// Colours come from the --viz-* variables in app.css (light and dark).

export type ChartSeries = { label: string; color: string; values: number[] };

type Point = { label: string; value: number; color?: string };

const AXIS_W = 40;
const TOP = 8;
const X_BAND = 20;

export function monthLabel(
    period: string,
    style: 'short' | 'narrow' | 'long' = 'short',
) {
    return new Date(`${period}-01T00:00:00`).toLocaleString('en', {
        month: style,
        ...(style === 'long' ? { year: 'numeric' } : {}),
    });
}

/** Shillings for an axis tick: 0, 500, 5K, 1.2M. */
export function compactKes(cents: number): string {
    return new Intl.NumberFormat('en', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(cents / 100);
}

function ticks(maxCents: number): number[] {
    const max = Math.max(maxCents / 100, 1);
    const rough = max / 3;
    const mag = 10 ** Math.floor(Math.log10(rough));
    const step =
        [1, 2, 5, 10].map((s) => s * mag).find((s) => s >= rough) ?? 10 * mag;
    const top = Math.ceil(max / step) * step;

    return Array.from(
        { length: Math.round(top / step) + 1 },
        (_, i) => i * step * 100,
    );
}

/** A column with a 4px rounded top and a square base on the baseline. */
function columnPath(x: number, y: number, w: number, h: number): string {
    if (h <= 0 || w <= 0) {
        return '';
    }

    const r = Math.min(4, w / 2, h);

    return `M${x},${y + h}V${y + r}Q${x},${y} ${x + r},${y}H${x + w - r}Q${x + w},${y} ${x + w},${y + r}V${y + h}Z`;
}

function useWidth() {
    const ref = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(0);

    useEffect(() => {
        const el = ref.current;

        if (!el) {
            return;
        }

        const observer = new ResizeObserver(([entry]) =>
            setWidth(Math.round(entry.contentRect.width)),
        );
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    return [ref, width] as const;
}

export function Legend({
    items,
}: {
    items: { label: string; color: string }[];
}) {
    return (
        <div className="viz flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            {items.map((item) => (
                <span
                    key={item.label}
                    className="inline-flex items-center gap-1.5"
                >
                    <span
                        className="inline-block size-2.5 rounded-[3px]"
                        style={{ background: item.color }}
                    />
                    {item.label}
                </span>
            ))}
        </div>
    );
}

/**
 * Monthly columns. With several series the columns stand side by side; with
 * `track`, the first series is drawn as a fill inside a lighter column of the
 * same hue (paid inside what was due).
 */
export function ColumnChart({
    months,
    series,
    track,
    trackLabel,
    label,
    height = 190,
}: {
    months: string[];
    series: ChartSeries[];
    track?: number[];
    trackLabel?: string;
    label: string;
    height?: number;
}) {
    const [ref, width] = useWidth();
    const [active, setActive] = useState<number | null>(null);

    const plotW = Math.max(0, width - AXIS_W);
    const plotH = height - TOP - X_BAND;
    const max = Math.max(
        0,
        ...series.flatMap((s) => s.values),
        ...(track ?? []),
    );
    const tickValues = ticks(max);
    const top = tickValues[tickValues.length - 1];
    const y = (v: number) => TOP + plotH - (v / top) * plotH;
    const band = plotW / months.length;
    const columns = track ? 1 : series.length;
    const gap = 2;
    const groupW = Math.min(band * 0.72, columns * 24 + (columns - 1) * gap);
    const barW = (groupW - (columns - 1) * gap) / columns;
    const narrow = band < 34;

    const rows = (i: number): Point[] => [
        ...(track ? [{ label: trackLabel ?? 'Total', value: track[i] }] : []),
        ...series.map((s) => ({
            label: s.label,
            value: s.values[i],
            color: s.color,
        })),
    ];

    return (
        <div
            ref={ref}
            // The svg is absolutely positioned so its pixel width never counts
            // towards the page's min width; otherwise the measured width can
            // only ever grow and the page scrolls sideways on phones.
            className="viz relative w-full min-w-0 select-none"
            style={{ height }}
            onPointerLeave={(e) => e.pointerType === 'mouse' && setActive(null)}
        >
            {width > 0 && (
                <svg
                    width={width}
                    height={height}
                    className="absolute inset-0"
                    role="img"
                    aria-label={label}
                >
                    {tickValues.map((t) => (
                        <g key={t}>
                            <line
                                x1={AXIS_W}
                                x2={width}
                                y1={y(t)}
                                y2={y(t)}
                                stroke={
                                    t === 0
                                        ? 'var(--viz-axis)'
                                        : 'var(--viz-grid)'
                                }
                                strokeWidth={1}
                                shapeRendering="crispEdges"
                            />
                            <text
                                x={AXIS_W - 6}
                                y={y(t)}
                                dy="0.32em"
                                textAnchor="end"
                                className="fill-muted-foreground text-[10px] tabular-nums"
                            >
                                {compactKes(t)}
                            </text>
                        </g>
                    ))}

                    {months.map((m, i) => {
                        const x0 = AXIS_W + i * band + (band - groupW) / 2;
                        const dim = active !== null && active !== i;

                        return (
                            <g key={m} opacity={dim ? 0.4 : 1}>
                                {track && (
                                    <path
                                        d={columnPath(
                                            x0,
                                            y(track[i]),
                                            barW,
                                            y(0) - y(track[i]),
                                        )}
                                        fill="var(--viz-track)"
                                    />
                                )}
                                {(track ? series.slice(0, 1) : series).map(
                                    (s, k) => {
                                        const v = s.values[i];

                                        return (
                                            <path
                                                key={s.label}
                                                d={columnPath(
                                                    x0 + k * (barW + gap),
                                                    y(v),
                                                    barW,
                                                    y(0) - y(v),
                                                )}
                                                fill={s.color}
                                            />
                                        );
                                    },
                                )}
                                <text
                                    x={AXIS_W + i * band + band / 2}
                                    y={height - 5}
                                    textAnchor="middle"
                                    className={cn(
                                        'text-[10px]',
                                        active === i
                                            ? 'fill-foreground font-semibold'
                                            : 'fill-muted-foreground',
                                    )}
                                >
                                    {monthLabel(m, narrow ? 'narrow' : 'short')}
                                </text>
                                <rect
                                    x={AXIS_W + i * band}
                                    y={TOP}
                                    width={band}
                                    height={plotH + X_BAND}
                                    fill="transparent"
                                    tabIndex={0}
                                    role="button"
                                    aria-label={`${monthLabel(m, 'long')}: ${rows(
                                        i,
                                    )
                                        .map(
                                            (r) =>
                                                `${r.label} ${formatKes(r.value)}`,
                                        )
                                        .join(', ')}`}
                                    className="cursor-pointer outline-none"
                                    onPointerEnter={(e) =>
                                        e.pointerType === 'mouse' &&
                                        setActive(i)
                                    }
                                    onClick={() => setActive(i)}
                                    onFocus={() => setActive(i)}
                                    onBlur={() => setActive(null)}
                                />
                            </g>
                        );
                    })}
                </svg>
            )}

            {active !== null && width > 0 && (
                <div
                    className="pointer-events-none absolute top-0 z-10 min-w-36 rounded-md border bg-popover px-2.5 py-2 text-xs text-popover-foreground shadow-md"
                    style={{
                        left: Math.min(
                            Math.max(AXIS_W + active * band + band / 2, 80),
                            width - 80,
                        ),
                        transform: 'translateX(-50%)',
                    }}
                >
                    <div className="mb-1 font-medium">
                        {monthLabel(months[active], 'long')}
                    </div>
                    {rows(active).map((r) => (
                        <div
                            key={r.label}
                            className="flex items-center justify-between gap-3"
                        >
                            <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                                <span
                                    className="inline-block size-2 rounded-[2px]"
                                    style={{
                                        background:
                                            r.color ?? 'var(--viz-track)',
                                    }}
                                />
                                {r.label}
                            </span>
                            <span className="tabular-nums">
                                {formatKes(r.value)}
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

/** Horizontal bars for comparing a handful of named amounts. */
export function BarList({
    items,
}: {
    items: { key: string | number; label: ReactNode; value: number }[];
}) {
    const max = Math.max(1, ...items.map((i) => i.value));

    return (
        <ul className="viz space-y-3">
            {items.map((item) => (
                <li key={item.key}>
                    <div className="flex items-baseline justify-between gap-3 text-sm">
                        <span className="min-w-0 break-words">
                            {item.label}
                        </span>
                        <span className="shrink-0 font-medium tabular-nums">
                            {formatKes(item.value)}
                        </span>
                    </div>
                    <div className="mt-1 h-2.5">
                        <div
                            className="h-full rounded-r-[4px]"
                            style={{
                                width: `${Math.max(1.5, (item.value / max) * 100)}%`,
                                background: 'var(--viz-1)',
                            }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

/** A thin bar showing part of a whole, track in a lighter step of the same hue. */
export function Meter({ value, max }: { value: number; max: number }) {
    const pct = max > 0 ? Math.min(100, (value / max) * 100) : 0;

    return (
        <div
            className="viz mt-2 h-1.5 overflow-hidden rounded-full"
            style={{ background: 'var(--viz-track)' }}
        >
            <div
                className="h-full rounded-full"
                style={{ width: `${pct}%`, background: 'var(--viz-1)' }}
            />
        </div>
    );
}

/** The same numbers as a chart, as a plain table for anyone who prefers it. */
export function ChartTable({
    columns,
    rows,
}: {
    columns: string[];
    rows: { key: string; cells: ReactNode[] }[];
}) {
    return (
        <details className="mt-3 text-sm">
            <summary className="cursor-pointer text-xs text-muted-foreground select-none">
                Show as table
            </summary>
            <div className="mt-2 overflow-x-auto">
                <table className="w-full text-xs">
                    <thead>
                        <tr className="border-b text-muted-foreground">
                            {columns.map((c, i) => (
                                <th
                                    key={c}
                                    className={cn(
                                        'py-1.5 font-medium',
                                        i === 0 ? 'text-left' : 'text-right',
                                    )}
                                >
                                    {c}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={r.key} className="border-b last:border-0">
                                {r.cells.map((cell, i) => (
                                    <td
                                        key={i}
                                        className={cn(
                                            'py-1.5',
                                            i === 0
                                                ? 'text-left'
                                                : 'text-right tabular-nums',
                                        )}
                                    >
                                        {cell}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </details>
    );
}
