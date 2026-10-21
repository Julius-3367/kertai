import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name, group } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-emerald-700 text-white">
                <AppLogoIcon className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold">
                    {name}
                </span>
                {group && (
                    <span className="truncate text-xs leading-tight text-muted-foreground">
                        {group.name}
                    </span>
                )}
            </div>
        </>
    );
}
