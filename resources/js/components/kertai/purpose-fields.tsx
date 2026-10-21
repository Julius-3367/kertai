import { Field, Select } from '@/components/kertai/fields';
import type { Option } from '@/types/kertai';

export type Targets = {
    fundraiser: Option[];
    project: Option[];
    hire: Option[];
    sale: Option[];
};

export const PURPOSES = [
    { value: 'contribution', label: 'Contributions, levies and fines' },
    { value: 'fundraiser', label: 'Fundraiser' },
    { value: 'project', label: 'Project contribution' },
    { value: 'sale', label: 'Payment for a sale' },
    { value: 'hire', label: 'Payment for a hire' },
    { value: 'donation', label: 'Donation to the group fund' },
];

/** "What is it for" plus the matching fundraiser/project/sale/hire picker. */
export function PurposeFields({
    purpose,
    targetId,
    onPurpose,
    onTarget,
    targets,
    errors,
    idPrefix,
}: {
    purpose: string;
    targetId: string;
    onPurpose: (v: string) => void;
    onTarget: (v: string) => void;
    targets: Targets;
    errors: { purpose?: string; target_id?: string };
    idPrefix: string;
}) {
    const options = targets[purpose as keyof Targets];

    return (
        <>
            <Field
                label="Paying for"
                htmlFor={`${idPrefix}-purpose`}
                error={errors.purpose}
            >
                <Select
                    id={`${idPrefix}-purpose`}
                    value={purpose}
                    onChange={(e) => {
                        onPurpose(e.target.value);
                        onTarget('');
                    }}
                    options={PURPOSES}
                />
            </Field>
            {options && (
                <Field
                    label={`Which ${purpose}`}
                    htmlFor={`${idPrefix}-target`}
                    error={errors.target_id}
                >
                    <Select
                        id={`${idPrefix}-target`}
                        value={targetId}
                        onChange={(e) => onTarget(e.target.value)}
                        placeholder={options.length ? 'Choose' : 'None open'}
                        options={options.map((o) => ({
                            value: o.id,
                            label: o.label,
                        }))}
                    />
                </Field>
            )}
        </>
    );
}
