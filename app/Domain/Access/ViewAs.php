<?php

namespace App\Domain\Access;

use App\Domain\Access\Models\Position;
use App\Models\User;

/**
 * Lets someone who manages positions see Kertai as another position (or as
 * an ordinary member) without changing anyone's access. While previewing,
 * the session holds the position and every change is blocked.
 */
final class ViewAs
{
    public const SESSION_KEY = 'view_as_position';

    /** Stored instead of a position id to preview an ordinary member. */
    public const MEMBER = 0;

    public static function active(): bool
    {
        return self::stored() !== null;
    }

    /**
     * The permissions to use instead of the user's own, or null when not
     * previewing (or not allowed to).
     *
     * @param  list<string>  $real
     * @return list<string>|null
     */
    public static function permissionsFor(User $user, array $real): ?array
    {
        $id = self::stored();

        if ($id === null || ! in_array(Permission::PositionsManage->value, $real, true)) {
            return null;
        }

        if ($id === self::MEMBER) {
            return [];
        }

        return Position::query()->find($id)->permissions ?? [];
    }

    public static function label(): ?string
    {
        $id = self::stored();

        return match (true) {
            $id === null => null,
            $id === self::MEMBER => 'an ordinary member',
            default => Position::query()->find($id)?->name,
        };
    }

    private static function stored(): ?int
    {
        if (! app()->bound('request') || ! request()->hasSession()) {
            return null;
        }

        $value = request()->session()->get(self::SESSION_KEY);

        return $value === null ? null : (int) $value;
    }
}
