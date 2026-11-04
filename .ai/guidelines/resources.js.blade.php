# Frontend UI stability

Every UI change keeps the layout still. Nothing may flash, jump, clip or reflow on first load, navigation, polling, or after an action. Target is 0 layout shift (CLS = 0).

## Loading

- A skeleton occupies exactly the loaded content's box: same height, line count and wrapping at every breakpoint, including the aside. It is visible in light and dark mode, popovers included.
- Delay skeletons by about 300ms so fast responses show nothing. Show one only while data is genuinely missing, never while refetching data you already have.
- Shared props that persist across pages (nav items, user context) use `Inertia::defer(...)->once()`, so client visits don't refetch them and flash.
- Polling and refresh buttons use partial reloads (`only: [...]`) of the page's own props. Never reload the whole page, and keep showing the old data until the new data arrives.

## Changing content

- Numbers, counts and countdowns use `tabular-nums` in fixed-width slots.
- Buttons whose label changes ("Saving…", "Confirming…") get a `min-w`. Disabled, loading and enabled states are the same size.
- Conditional UI (banners, inline confirms, errors) reserves its space or overlays. An inline confirm fits in the slot of the control it replaces.
- Removed list rows collapse with animated transition, keeping scroll position and focus.
- Right after an action, the control under the cursor must not flip to an unexpected destructive state.

## Routing and Wayfinder

- Always use Laravel Wayfinder typed route functions imported from `@/actions/` or `@/routes/`.
- Never use hardcoded URL strings or string concatenation for backend endpoints.

## Overflow & Responsive Viewports

- Dialogs and popovers are capped to the viewport (`dvh`) and scroll inside, with a sticky header and footer when actions would scroll away. Scroll lock must keep `scrollbar-gutter: stable`.
- Long usernames, URLs, and labels use `min-w-0` with `truncate` or `line-clamp`, with the full text in `title`.
- Auto-growing textareas are `overflow-x-hidden`. Inputs use at least 16px text below `md` so iOS doesn't zoom.

## Verification

- Check in a browser at 1440×900, 1366×650 and 390×844, in both light and dark modes.
- Measure layout shift (`PerformanceObserver` with `layout-shift`) for first load, navigation, and actions. Target is 0.
