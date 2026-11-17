export type GenerationStatus =
    | 'pending'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

export type Generation = {
    id: string;
    status: GenerationStatus;
    prompt: string;
    size: string;
    quality: string;
    reference_images: number;
    estimated_min: number;
    estimated_max: number;
    price: number | null;
    tokens?: {
        input_text: number;
        input_image: number;
        output: number;
        reasoning: number;
    };
    image_url: string | null;
    image_expired: boolean;
    error_code: string | null;
    error_message: string | null;
    submission_attempts: number;
    created_at: string;
    completed_at: string | null;
};

export type Option = {
    value: string;
    label: string;
};

export type Estimate = {
    min: number;
    max: number;
};
