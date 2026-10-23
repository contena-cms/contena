import { vi } from 'vitest';

export class ContenaComponent {
    static options: Record<string, unknown> = {};

    el: HTMLElement;
    options: Record<string, unknown>;

    constructor(el: HTMLElement, options: Record<string, unknown> = {}) {
        this.el = el;
        this.options = { ...(new.target as typeof ContenaComponent | undefined)?.options, ...options };
    }

    init(): void {}
    destroy(): void {}

    /** Fires a CustomEvent on this.el so listeners added via addEventListener() receive it. */
    dispatchEvent(eventName: string, detail: Record<string, unknown> = {}): void {
        this.el.dispatchEvent(new CustomEvent(eventName, { detail, bubbles: true }));
    }

    /** In tests, debounce is a no-op passthrough so handlers fire synchronously. */
    debounce<T extends (...args: unknown[]) => unknown>(fn: T, _delay?: number): T {
        return fn;
    }
}

export const Contena = {
    emit: vi.fn(),
    on: vi.fn(),
    off: vi.fn(),
    emitInterception: vi.fn().mockImplementation((_event: string, payload: unknown) => payload),
    intercept: vi.fn(),
    emitQueued: vi.fn(),
    serializeForm: vi.fn().mockReturnValue({}),
};

// Mirror what the real contena.ts does so legacy components using
// ({ Contena, ContenaComponent } = window) also work in tests.
(globalThis as Record<string, unknown>).ContenaComponent = ContenaComponent;
(globalThis as Record<string, unknown>).Contena = Contena;
