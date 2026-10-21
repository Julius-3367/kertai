import { Field, Select } from '@/components/kertai/fields';
import { Input } from '@/components/ui/input';

const DURATIONS = [30, 60, 90, 120, 180, 240].map((m) => ({
    value: String(m),
    label: m < 60 ? `${m} minutes` : `${m / 60} hour${m === 60 ? '' : 's'}`,
}));

/**
 * The link members tap to join, and how long to block in their calendars.
 */
export function OnlineMeetingFields({
    idPrefix,
    link,
    duration,
    onLink,
    onDuration,
    linkError,
}: {
    idPrefix: string;
    link: string;
    duration: string;
    onLink: (value: string) => void;
    onDuration: (value: string) => void;
    linkError?: string;
}) {
    return (
        <>
            <Field
                label="Online meeting link (optional)"
                htmlFor={`${idPrefix}-link`}
                error={linkError}
                hint="In the Google Meet app tap New → Get a meeting link to share, copy it and paste it here. Zoom and Teams links work too."
            >
                <Input
                    id={`${idPrefix}-link`}
                    type="url"
                    inputMode="url"
                    placeholder="https://meet.google.com/abc-defg-hij"
                    value={link}
                    onChange={(e) => onLink(e.target.value.trim())}
                />
            </Field>
            <Field label="How long" htmlFor={`${idPrefix}-duration`}>
                <Select
                    id={`${idPrefix}-duration`}
                    value={duration}
                    onChange={(e) => onDuration(e.target.value)}
                    options={DURATIONS}
                />
            </Field>
        </>
    );
}
