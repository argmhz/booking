import { Config } from 'ziggy-js';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    roles: string[];
    notifications?: {
        unread_count: number;
        items: {
            id: string;
            title: string;
            message: string;
            url: string;
            created_at: string;
            read_at: string | null;
        }[];
    };
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
    };
    flash: {
        status?: string;
    };
    ziggy: Config & { location: string };
};
