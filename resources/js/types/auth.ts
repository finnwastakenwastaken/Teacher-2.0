export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
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

/**
 * What App\Support\PasswordPolicy::describe() sends. Rendered as a checklist
 * beside every new-password field so the requirements are visible before
 * submitting rather than revealed one failed attempt at a time.
 *
 * The server remains the authority; this only decides what to draw.
 */
export type PasswordPolicy = {
    min: number;
    letters: boolean;
    mixedCase: boolean;
    numbers: boolean;
    symbols: boolean;
    uncompromised: boolean;
};
