<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        $data = parent::toArray($request);
        
        if (isset($data['image']) && $data['image']) {
            if (!filter_var($data['image'], FILTER_VALIDATE_URL)) {
                $data['image'] = url($data['image']);
            }
        }
        
        if (isset($data['images']) && $data['images']) {
            $images = is_string($data['images']) ? json_decode($data['images'], true) : $data['images'];
            if (is_array($images)) {
                $data['images'] = array_map(function($image) {
                    if (!filter_var($image, FILTER_VALIDATE_URL)) {
                        return url($image);
                    }
                    return $image;
                }, $images);
            }
        }

        return $data;
    }
}

