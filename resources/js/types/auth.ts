export type User = {
    id: number;
    name: string;
    email: string | null;
    phone?: string | null;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type AuthMember = {
    id: number;
    name: string;
    member_number: string;
    status: string;
    positions: string[];
    has_loans: boolean;
};

export type Auth = {
    user: User;
    member: AuthMember | null;
    permissions: string[];
    /** The position being previewed with "View as", if any. */
    viewing_as: string | null;
    unread: number;
};

export type SharedGroup = {
    name: string;
    paybill: string | null;
    paybill_account: string | null;
    open_books: boolean;
    loans_enabled: boolean;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
