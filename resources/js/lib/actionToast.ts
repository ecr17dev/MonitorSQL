import { toast } from 'vue-sonner';

type ErrorBag = Record<string, string | string[]>;

function firstErrorFromBag(errors: ErrorBag): string | null {
    for (const value of Object.values(errors)) {
        if (Array.isArray(value) && value.length > 0 && typeof value[0] === 'string') {
            return value[0];
        }

        if (typeof value === 'string' && value.trim() !== '') {
            return value;
        }
    }

    return null;
}

export function extractErrorMessage(error: unknown, fallback: string): string {
    if (error instanceof Error && error.message.trim() !== '') {
        return error.message;
    }

    if (typeof error === 'string' && error.trim() !== '') {
        return error;
    }

    if (typeof error === 'object' && error !== null) {
        const candidate = error as { message?: unknown; errors?: unknown };

        if (typeof candidate.message === 'string' && candidate.message.trim() !== '') {
            return candidate.message;
        }

        if (typeof candidate.errors === 'object' && candidate.errors !== null) {
            const formMessage = firstErrorFromBag(candidate.errors as ErrorBag);

            if (formMessage !== null) {
                return formMessage;
            }
        }
    }

    return fallback;
}

export function toastActionError(error: unknown, fallback = 'No se pudo completar la acción.'): void {
    toast.error(extractErrorMessage(error, fallback));
}

export function toastActionWarning(message: string): void {
    toast.warning(message);
}

export function toastActionSuccess(message: string): void {
    toast.success(message);
}
