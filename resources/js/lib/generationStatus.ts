import type { Generation } from '@/types';

export type StatusPresentation = {
    label: string;
    pill: string;
    tone: string;
    detail: string;
};

const FAILED_FALLBACK = 'Pixelforge could not create this image.';

export function presentStatus(generation: Generation): StatusPresentation {
    switch (generation.status) {
        case 'pending':
            return generation.submission_attempts > 1
                ? {
                      label: '◌ Waiting for capacity',
                      pill: 'bg-amber-300 text-studio',
                      tone: 'text-amber-300',
                      detail: `Pixelforge is busy, retrying (attempt ${generation.submission_attempts})`,
                  }
                : {
                      label: '◌ Sending',
                      pill: 'bg-amber-300 text-studio',
                      tone: 'text-amber-300',
                      detail: 'Sending to Pixelforge',
                  };
        case 'queued':
            return {
                label: '◌ Queued',
                pill: 'bg-amber-300 text-studio',
                tone: 'text-amber-300',
                detail: 'Waiting in Pixelforge’s queue',
            };
        case 'processing':
            return {
                label: '● Processing',
                pill: 'bg-lime text-studio',
                tone: 'text-lime',
                detail: 'Rendering…',
            };
        case 'succeeded':
            return {
                label: '✓ Done',
                pill: 'bg-green-950 text-green-200',
                tone: 'text-studio-muted',
                detail: 'The image link expired 24 hours after it was made',
            };
        default:
            return {
                label: '✕ Failed',
                pill: 'bg-red-950 text-red-200',
                tone: 'text-red-300',
                detail: generation.error_message || FAILED_FALLBACK,
            };
    }
}

export function describeSettings(generation: Generation): string {
    const references = generation.reference_images
        ? `${generation.reference_images} reference${generation.reference_images > 1 ? 's' : ''}`
        : null;

    return [generation.quality, generation.size, references]
        .filter(Boolean)
        .join(' · ');
}
