<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public function has_items(){
        return  $this->hasMany(Lineitem::class, 'shopify_id', 'shopify_order_id');
    }
}
