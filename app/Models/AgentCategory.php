<?php

 

namespace App\Models;

 

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\HasMany;

 

class AgentCategory extends Model

{

    use HasFactory, HasUuids;

 

    /**

     * The attributes that are mass assignable.

     *

     * @var array<int, string>

     */

    protected $fillable = [

        'name',

        'slug',

        'description',

        'icon',

        'display_order',

    ];

 

    /**

     * The attributes that should be cast.

     *

     * @var array<string, string>

     */

    protected $casts = [

        'display_order' => 'integer',

    ];

 

    /**

     * Get agents in this category.

     */

    public function agents(): HasMany

    {

        return $this->hasMany(Agent::class, 'category_id');

    }

}