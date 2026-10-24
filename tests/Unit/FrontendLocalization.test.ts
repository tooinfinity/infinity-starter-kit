/// <reference types="node" />
import assert from 'node:assert/strict';
import { afterEach, mock, test } from 'node:test';
import { App } from '@inertiajs/react';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import ar from '../../lang/ar.json';
import en from '../../lang/en.json';
import fr from '../../lang/fr.json';
import { ReportChart } from '../../resources/js/components/reports/report-chart';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import { useTranslation } from '../../resources/js/hooks/use-translation';
import {
    formatDate,
    formatRelativeTime,
} from '../../resources/js/lib/formatters';

const now = new Date('2026-10-24T12:00:00Z');

afterEach(() => mock.timers.reset());

function renderPage(
    Component: () => ReturnType<typeof createElement>,
    locale: string,
    lang: Record<string, string>,
) {
    return renderToStaticMarkup(
        createElement(App, {
            initialPage: {
                component: 'Test',
                props: { locale, lang, errors: {} },
                rescuedProps: [],
                flash: {},
                rememberedState: {},
                url: '/',
                version: null,
                clearHistory: false,
                encryptHistory: false,
            },
            initialComponent: Component,
        }),
    );
}

for (const [offsetHours, expected] of [
    [-0.5, '30 minutes ago'],
    [0.5, 'in 30 minutes'],
    [-1, '1 hour ago'],
    [1, 'in 1 hour'],
    [-2, '2 hours ago'],
    [2, 'in 2 hours'],
    [-24, 'yesterday'],
    [24, 'tomorrow'],
    [-48, '2 days ago'],
    [48, 'in 2 days'],
] as const) {
    await test(`relative time chooses the unit and direction for ${offsetHours} hours`, () => {
        mock.timers.enable({ apis: ['Date'], now });
        const date = new Date(now.getTime() + offsetHours * 3600000);
        assert.equal(
            formatRelativeTime(date.toISOString(), 'en', 'UTC'),
            expected,
        );
    });
}

await test('older relative dates use the application calendar date across midnight', () => {
    mock.timers.enable({ apis: ['Date'], now });
    const date = '2026-10-01T00:30:00Z';
    assert.equal(
        formatRelativeTime(date, 'en', 'America/Los_Angeles'),
        '9/30/2026',
    );
    assert.equal(
        formatRelativeTime(date, 'fr', 'America/Los_Angeles'),
        formatDate(date, 'fr', 'America/Los_Angeles'),
    );
    assert.equal(formatRelativeTime(date, 'en', 'UTC'), '10/1/2026');
});

await test('invalid and missing relative dates stay empty', () => {
    for (const value of [null, undefined, '', 'invalid']) {
        assert.equal(formatRelativeTime(value, 'en', 'UTC'), '');
    }
});

for (const [locale, lang, counts] of [
    [
        'en',
        en,
        [
            [0, '0 users'],
            [1, '1 user'],
            [2, '2 users'],
        ],
    ],
    [
        'fr',
        fr,
        [
            [0, '0 utilisateur'],
            [1, '1 utilisateur'],
            [2, '2 utilisateurs'],
        ],
    ],
    [
        'ar',
        ar,
        [
            [0, '0 مستخدم'],
            [1, '1 مستخدم'],
            [2, '2 مستخدمان'],
            [3, '3 مستخدمين'],
            [11, '11 مستخدمًا'],
            [100, '100 مستخدم'],
            [103, '103 مستخدمين'],
            [111, '111 مستخدمًا'],
        ],
    ],
] as const) {
    for (const [count, expected] of counts) {
        await test(`users count selects ${locale} plural form for ${count}`, () => {
            function Message() {
                const { transChoice } = useTranslation();
                return createElement(
                    'span',
                    null,
                    transChoice('users.total', count, { count }),
                );
            }
            assert.equal(
                renderPage(Message, locale, lang),
                `<span>${expected}</span>`,
            );
        });
    }
}

await test('plural messages allow translators to position named replacements', () => {
    function Message() {
        const { transChoice } = useTranslation();
        return createElement(
            'span',
            null,
            transChoice('users.total', 3, { label: 'Members' }),
        );
    }
    assert.equal(
        renderPage(Message, 'en', {
            'users.total': '{one} :label (:count)|{other} :label (:count)',
        }),
        '<span>Members (3)</span>',
    );
});

await test('existing binary, exact-count, range, and nested plural messages retain their behavior', () => {
    function Message() {
        const { transChoice, trans_choice } = useTranslation();
        return createElement(
            'span',
            null,
            [
                transChoice('binary', 1),
                transChoice('binary', 2),
                transChoice('ranges', 0),
                transChoice('ranges', 5),
                trans_choice('nested.label', 1),
            ].join(', '),
        );
    }
    assert.equal(
        renderPage(Message, 'fr', {
            binary: 'one|many',
            ranges: '{0} none|[1,9] :count items|[10,*] lots',
            'nested.label': 'nested one|nested many',
        }),
        '<span>one, many, none, 5 items, nested one</span>',
    );
});

for (const [locale, lang, total] of [
    ['en', en, 'Total: 12,345'],
    ['fr', fr, 'Total : 12\u202f345'],
    ['ar', ar, `الإجمالي: ${new Intl.NumberFormat('ar').format(12345)}`],
] as const) {
    await test(`chart total uses the selected ${locale} locale`, () => {
        function Chart() {
            return createElement(
                TooltipProvider,
                null,
                createElement(ReportChart, {
                    title: 'Users',
                    description: 'Registrations',
                    series: [{ date: '2026-10-01', value: 12345 }],
                }),
            );
        }
        assert.ok(renderPage(Chart, locale, lang).includes(total));
    });
}
