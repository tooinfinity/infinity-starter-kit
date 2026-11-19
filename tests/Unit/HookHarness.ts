import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';

/**
 * Renders a component as a plain function, keeping its hooks' state by call order across renders as React does,
 * running effects when their dependencies change and cleaning up the previous run, so a page's lifecycle can be
 * driven without a DOM. Only the component given is run: the elements it returns are inspected, not rendered.
 */
export function hookHarness() {
    let slots: unknown[] = [];
    let slot = 0;
    let rerender: (() => void) | null = null;

    const next = () => slot++;

    const react = {
        ...React,
        useState: (initial: unknown) => {
            const at = next();

            if (!(at in slots)) {
                slots[at] =
                    typeof initial === 'function'
                        ? (initial as () => unknown)()
                        : initial;
            }

            return [
                slots[at],
                (value: unknown) => {
                    slots[at] =
                        typeof value === 'function'
                            ? (value as (current: unknown) => unknown)(slots[at])
                            : value;
                },
            ];
        },
        useRef: (current: unknown) => {
            const at = next();

            if (!(at in slots)) {
                slots[at] = { current };
            }

            return slots[at];
        },
        useEffect: (effect: () => (() => void) | void, deps?: unknown[]) => {
            const at = next();
            const previous = slots[at] as
                | { deps?: unknown[]; cleanup?: (() => void) | void }
                | undefined;

            if (
                previous === undefined ||
                deps === undefined ||
                previous.deps === undefined ||
                deps.some(
                    (dep, index) => !Object.is(dep, previous.deps?.[index]),
                )
            ) {
                if (typeof previous?.cleanup === 'function') {
                    previous.cleanup();
                }

                slots[at] = { deps, cleanup: effect() };
            }
        },
        useLayoutEffect: () => {
            next();
        },
        useEffectEvent: (callback: (...args: unknown[]) => unknown) => {
            const at = next();

            if (!(at in slots)) {
                const holder = {
                    callback,
                    call: (...args: unknown[]) => holder.callback(...args),
                };
                slots[at] = holder;
            }

            const holder = slots[at] as {
                callback: typeof callback;
                call: (...args: unknown[]) => unknown;
            };
            holder.callback = callback;

            return holder.call;
        },
        useSyncExternalStore: (_subscribe: unknown, read: () => unknown) =>
            read(),
        useId: () => `id-${next()}`,
        useMemo: (factory: () => unknown) => factory(),
        useCallback: (callback: unknown) => callback,
    };

    return {
        react,
        /**
         * Runs the component with the given props, and keeps them to run it again with `rerender`.
         */
        render<P>(
            component: (props: P) => ReactNode,
            props: P,
        ): ReactElement<Record<string, unknown>> | null {
            slot = 0;
            const tree = component(props);
            rerender = () => {
                slot = 0;
                void component(props);
            };

            return tree as ReactElement<Record<string, unknown>> | null;
        },
        rerender: () => rerender?.(),
        unmount: () => {
            slots.forEach((value) => {
                const cleanup = (value as { cleanup?: unknown } | undefined)
                    ?.cleanup;

                if (typeof cleanup === 'function') {
                    cleanup();
                }
            });
            slots = [];
        },
    };
}

export function find(
    node: ReactNode,
    matches: (props: Record<string, unknown>) => boolean,
): ReactElement<Record<string, unknown>> | null {
    if (Array.isArray(node)) {
        for (const child of node) {
            const found = find(child, matches);

            if (found) {
                return found;
            }
        }

        return null;
    }

    if (!React.isValidElement<Record<string, unknown>>(node)) {
        return null;
    }

    if (matches(node.props)) {
        return node;
    }

    if (typeof node.props.children === 'function') {
        try {
            const rendered = (node.props.children as (arg: unknown) => ReactNode)({
                processing: false,
            });
            const found = find(rendered, matches);
            if (found) {
                return found;
            }
        } catch {
            // Render prop may require specific arguments
        }
    }

    return find(node.props.children as ReactNode, matches);
}

export function findAll(
    node: ReactNode,
    matches: (props: Record<string, unknown>) => boolean,
): Array<ReactElement<Record<string, unknown>>> {
    const results: Array<ReactElement<Record<string, unknown>>> = [];

    function walk(childNode: ReactNode): void {
        if (Array.isArray(childNode)) {
            for (const child of childNode) {
                walk(child);
            }
            return;
        }

        if (!React.isValidElement<Record<string, unknown>>(childNode)) {
            return;
        }

        if (matches(childNode.props)) {
            results.push(childNode);
        }

        if (typeof childNode.props.children === 'function') {
            try {
                const rendered = (
                    childNode.props.children as (arg: unknown) => ReactNode
                )({ processing: false });
                walk(rendered);
            } catch {
                // Render prop may require specific arguments
            }
        }

        walk(childNode.props.children as ReactNode);
    }

    walk(node);
    return results;
}
