<?php

namespace App\Http\Requests;

use App\Data\DateRange;
use App\Repositories\Contracts\DailyAverageRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CleanestCityRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.required' => 'Choose a start date.',
            'to.required' => 'Choose an end date.',
            'from.date_format' => 'The start date must be in the YYYY-MM-DD format.',
            'to.date_format' => 'The end date must be in the YYYY-MM-DD format.',
            'from.before_or_equal' => 'The start date cannot be in the future.',
            'to.before_or_equal' => 'The end date cannot be in the future.',
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }

    /**
     * @param  DailyAverageRepositoryInterface  $averages
     * @return list<callable(Validator): void>
     */
    public function after(DailyAverageRepositoryInterface $averages): array
    {
        return [
            function (Validator $validator) use ($averages): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $available = $averages->availableRange();

                if ($available === null) {
                    $validator->errors()->add('from', 'No air quality data has been imported yet.');

                    return;
                }

                if (! $available->contains($this->period())) {
                    $validator->errors()->add(
                        'from',
                        "Data is available from {$available->from->toDateString()} to {$available->to->toDateString()}.",
                    );
                }
            },
        ];
    }

    /**
     * @return DateRange
     */
    public function period(): DateRange
    {
        return DateRange::fromStrings((string) $this->input('from'), (string) $this->input('to'));
    }
}
