<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\Review\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use MultiTenantSaas\Modules\Review\Models\Review;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', Rule::in(Review::ENTITY_TYPES)],
            'entity_id' => 'required|integer|min:1',
            'rating' => 'required|integer|min:1|max:5',
            'content' => 'nullable|string|max:2000',
            'images' => 'nullable|array|max:9',
            'images.*' => 'required|string|max:500',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entity_type.required' => '评价对象类型不能为空',
            'entity_type.in' => '评价对象类型不在支持范围内',
            'entity_id.required' => '评价对象 ID 不能为空',
            'rating.required' => '评分不能为空',
            'rating.min' => '评分不能低于 1 分',
            'rating.max' => '评分不能高于 5 分',
            'images.max' => '最多上传 9 张图片',
        ];
    }
}
