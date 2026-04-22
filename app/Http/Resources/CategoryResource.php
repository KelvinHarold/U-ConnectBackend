<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        $data = parent::toArray($request);
        
        if (isset($data['image']) && $data['image']) {
            if (!filter_var($data['image'], FILTER_VALIDATE_URL)) {
                $data['image'] = url($data['image']);
            }
        }

        return $data;
    }
}
