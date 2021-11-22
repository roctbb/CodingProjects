<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CoreVersion1FlipEdges extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $edges = App\CoreEdge::all()->filter(function ($i) {
            return $i->from->version == 1;
        });

        foreach($edges as $edge)
        {
            $from_tmp = $edge->from_id;
            $edge->from_id = $edge->to_id;
            $edge->to_id = $from_tmp;
            $edge->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
