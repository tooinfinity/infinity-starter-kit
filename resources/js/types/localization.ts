export type Direction = 'ltr' | 'rtl';

export type LocaleOption = {
    code: string;
    name: string;
    nativeName: string;
    direction: Direction;
};

declare global {
    interface SharedPageProps {
        locale: string;
        direction: Direction;
        supportedLocales: LocaleOption[];
        lang?: Record<string, unknown>;
    }
}
