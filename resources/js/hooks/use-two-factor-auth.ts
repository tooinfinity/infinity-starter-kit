import { useCallback, useRef, useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { qrCode, recoveryCodes, secretKey } from '@/routes/two-factor';
import type { TwoFactorSecretKey, TwoFactorSetupData } from '@/types';

export type UseTwoFactorAuthReturn = {
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    recoveryCodesList: string[];
    hasSetupData: boolean;
    errors: string[];
    requiresPasswordConfirmation: boolean;
    clearErrors: () => void;
    clearSetupData: () => void;
    fetchQrCode: () => Promise<void>;
    fetchSetupKey: () => Promise<void>;
    fetchSetupData: () => Promise<void>;
    fetchRecoveryCodes: () => Promise<void>;
};

export const OTP_MAX_LENGTH = 6;

export class HttpError extends Error {
    constructor(
        public readonly status: number,
        message?: string,
    ) {
        super(message ?? `HTTP error ${status}`);
        this.name = 'HttpError';
    }
}

const fetchJson = async <T>(url: string, signal?: AbortSignal): Promise<T> => {
    const response = await fetch(url, {
        signal,
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        let message: string | undefined;
        try {
            const data = (await response.json()) as { message?: string };
            message = data?.message;
        } catch {
            // Ignore JSON parse errors
        }

        throw new HttpError(
            response.status,
            message ?? `Failed to fetch: ${response.status}`,
        );
    }

    return response.json();
};

export const useTwoFactorAuth = (): UseTwoFactorAuthReturn => {
    const { __ } = useTranslation();
    const [qrCodeSvg, setQrCodeSvg] = useState<string | null>(null);
    const [manualSetupKey, setManualSetupKey] = useState<string | null>(null);
    const [recoveryCodesList, setRecoveryCodesList] = useState<string[]>([]);
    const [errors, setErrors] = useState<string[]>([]);
    const [requiresPasswordConfirmation, setRequiresPasswordConfirmation] =
        useState<boolean>(false);
    const setupAttemptRef = useRef<number>(0);
    const setupAbortControllerRef = useRef<AbortController | null>(null);

    const hasSetupData = qrCodeSvg !== null && manualSetupKey !== null;

    const fetchQrCode = useCallback(async (): Promise<void> => {
        const attemptId = ++setupAttemptRef.current;
        try {
            const { svg } = await fetchJson<TwoFactorSetupData>(qrCode.url());
            if (setupAttemptRef.current === attemptId) {
                setQrCodeSvg(svg);
            }
        } catch (error) {
            if (setupAttemptRef.current === attemptId) {
                if (error instanceof HttpError && error.status === 423) {
                    setRequiresPasswordConfirmation(true);
                    setErrors([__('Password confirmation required.')]);
                } else {
                    setErrors((prev) => [
                        ...prev,
                        __('Failed to fetch QR code'),
                    ]);
                }
                setQrCodeSvg(null);
            }
        }
    }, [__]);

    const fetchSetupKey = useCallback(async (): Promise<void> => {
        const attemptId = ++setupAttemptRef.current;
        try {
            const { secretKey: key } = await fetchJson<TwoFactorSecretKey>(
                secretKey.url(),
            );
            if (setupAttemptRef.current === attemptId) {
                setManualSetupKey(key);
            }
        } catch (error) {
            if (setupAttemptRef.current === attemptId) {
                if (error instanceof HttpError && error.status === 423) {
                    setRequiresPasswordConfirmation(true);
                    setErrors([__('Password confirmation required.')]);
                } else {
                    setErrors((prev) => [
                        ...prev,
                        __('Failed to fetch a setup key'),
                    ]);
                }
                setManualSetupKey(null);
            }
        }
    }, [__]);

    const clearErrors = useCallback((): void => {
        setErrors([]);
        setRequiresPasswordConfirmation(false);
    }, []);

    const clearSetupData = useCallback((): void => {
        setupAbortControllerRef.current?.abort();
        setupAttemptRef.current++;
        setManualSetupKey(null);
        setQrCodeSvg(null);
        clearErrors();
    }, [clearErrors]);

    const fetchRecoveryCodes = useCallback(async (): Promise<void> => {
        try {
            clearErrors();
            const codes = await fetchJson<string[]>(recoveryCodes.url());
            setRecoveryCodesList(codes);
        } catch (error) {
            if (error instanceof HttpError && error.status === 423) {
                setRequiresPasswordConfirmation(true);
                setErrors([__('Password confirmation required.')]);
            } else {
                setErrors((prev) => [
                    ...prev,
                    __('Failed to fetch recovery codes'),
                ]);
            }
            setRecoveryCodesList([]);
        }
    }, [__, clearErrors]);

    const fetchSetupData = useCallback(async (): Promise<void> => {
        setupAbortControllerRef.current?.abort();
        const controller = new AbortController();
        setupAbortControllerRef.current = controller;
        const { signal } = controller;

        const attemptId = ++setupAttemptRef.current;
        clearErrors();

        let qrError: string | null = null;
        let keyError: string | null = null;

        const handle423 = () => {
            if (setupAttemptRef.current !== attemptId) {
                return;
            }

            controller.abort();
            setRequiresPasswordConfirmation(true);
            setErrors([__('Password confirmation required.')]);
            setQrCodeSvg(null);
            setManualSetupKey(null);
        };

        const qrPromise = fetchJson<TwoFactorSetupData>(qrCode.url(), signal)
            .then((data) => {
                if (setupAttemptRef.current !== attemptId) {
                    return;
                }
                setQrCodeSvg(data.svg);
            })
            .catch((error: unknown) => {
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                ) {
                    return;
                }
                if (setupAttemptRef.current !== attemptId) {
                    return;
                }
                if (error instanceof HttpError && error.status === 423) {
                    handle423();
                    return;
                }
                qrError = __('Failed to fetch QR code');
                setQrCodeSvg(null);
            });

        const keyPromise = fetchJson<TwoFactorSecretKey>(
            secretKey.url(),
            signal,
        )
            .then((data) => {
                if (setupAttemptRef.current !== attemptId) {
                    return;
                }
                setManualSetupKey(data.secretKey);
            })
            .catch((error: unknown) => {
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                ) {
                    return;
                }
                if (setupAttemptRef.current !== attemptId) {
                    return;
                }
                if (error instanceof HttpError && error.status === 423) {
                    handle423();
                    return;
                }
                keyError = __('Failed to fetch a setup key');
                setManualSetupKey(null);
            });

        await Promise.allSettled([qrPromise, keyPromise]);

        if (setupAttemptRef.current === attemptId && !signal.aborted) {
            const newErrors: string[] = [];
            if (qrError) {
                newErrors.push(qrError);
            }
            if (keyError) {
                newErrors.push(keyError);
            }
            if (newErrors.length > 0) {
                setErrors((prev) => [...prev, ...newErrors]);
            }
        }
    }, [__, clearErrors]);

    return {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        hasSetupData,
        errors,
        requiresPasswordConfirmation,
        clearErrors,
        clearSetupData,
        fetchQrCode,
        fetchSetupKey,
        fetchSetupData,
        fetchRecoveryCodes,
    };
};
