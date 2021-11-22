<?php
/**
 * Created by PhpStorm.
 * User: AlexNerru
 * Date: 03.09.2017
 * Time: 23:22
 */

namespace App\Http\Controllers;
use App\CoreEdge;
use App\CoreNode;
use App\Project;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Auth;
use Carbon\Carbon;
use function GuzzleHttp\json_encode;

class CoreController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('admin')->only(['import_core']);
    }

    public function index($id, Request $request)
    {
        $version = 1;
        if ($request->has('version'))
        {
            $version = $request->version;
        }
        return view('core.index', compact('id', 'version'));
    }

    public function get_versions()
    {
        return json_encode(\App\CoreNode::All()->groupBy('version')->keys());
    }

    public function get_core($id, Request $request)
    {
        $version=1;
        $user = User::findOrFail($id);
        if ($request->has('version'))
        {
            $version = $request->version;
        }

        $nodes = CoreNode::where('version', $version)->get();
        foreach ($nodes as $node)
        {
            if ($user->checkPrerequisite($node))
            {
                $node->nodeType='use';
            }
            else {
                $node->nodeType='exists';
            }
            $node->root = $node->is_root;
        }
        $edges = CoreEdge::whereIn('from_id', $nodes->pluck('id'))->orWhereIn('to_id', $nodes->pluck('id'))->get();

        foreach ($edges as $edge)
        {
            $edge->source = $edge->from_id;
            $edge->target = $edge->to_id;
            unset($edge->id);
        }


        $result = ['nodes' => $nodes, 'edges' => $edges];
        return json_encode($result);
    }

    public function editor()
    {
        return view('core/editor');
    }

    public function subcore($id, $node_id, Request $request)
    {
        $version=1;
        $user = User::findOrFail($id);
        $start_node = CoreNode::findOrFail($node_id);
        $nodes = collect([]);
        $nodes->push($start_node);

        $nodes_to_look = $start_node->parents;

        while ($nodes_to_look->count() != 0)
        {
            $current_node = $nodes_to_look->pop();
            $nodes->push($current_node);

            foreach ($current_node->parents as $parent)
            {
                if ($nodes->contains($parent) or $nodes_to_look->contains($parent)) continue;
                $nodes_to_look->push($parent);
            }
        }

        foreach ($nodes as $node)
        {
            if ($node == $start_node)
            {
                $node->nodeType='target';
            }
            else if ($user->checkPrerequisite($node))
            {
                $node->nodeType='use';
            }
            else {
                $node->nodeType='exists';
            }
            $node->root = $node->is_root;
            unset($node->parents);
            unset($node->pivot);


        }
        $edges = CoreEdge::whereIn('from_id', $nodes->pluck('id'))->whereIn('to_id', $nodes->pluck('id'))->get();

        foreach ($edges as $edge)
        {
            $edge->source = $edge->from_id;
            $edge->target = $edge->to_id;
            unset($edge->id);
        }


        $result = ['nodes' => $nodes, 'edges' => $edges];
        return json_encode($result);
    }

    public function import_core_form() {
       return "<form method='post'>".csrf_field()."<textarea name='data'></textarea><br>Версия:<input type='text' value='1' name='version'/> <input type='submit'/></form>";
    }
    public function import_core(Request $request)
    {
        $data = json_decode($request->data);
        $nodeAutoIdc = [];
        // dd($data->nodes);

        $nodeIdc = collect($data->nodes)->map(function ($item, $index) { 
            return $item->id; 
        })->filter(function($item, $key) {
            return $item != -1;
        });
        CoreNode::where("version", $request->version)->whereNotIn("id", $nodeIdc)->delete();

        $edgeIdc = collect($data->edges)->map(function ($item, $index) { 
            return $item->id; 
        })->filter(function($item, $key) {
            return $item != -1;
        });

        CoreEdge::all()->filter(function($i) use ($request) { $i->from->version = $request->version; })->whereNotIn("id", $edgeIdc)->each->delete();
    
        $allNodes = CoreNode::all();

        foreach ($data->nodes as $node)
        {
            $fnode = null;
            if($node->id != -1)
            {
                $fnode = $allNodes->find($node->id);
            }
            if (!empty($fnode))
            {
                $fnode->title = $node->title;
                $fnode->level = $node->level;
                $fnode->cluster = $node->cluster;
                $fnode->is_root = $node->root;
                $fnode->version = $request->version;
                $fnode->save();

                $nodeAutoIdc[$node->localId] = $fnode->id; 
                
            }
            else
            {
                $record = new CoreNode();
                $record->title = $node->title;
                $record->cluster = $node->cluster;
                $record->level = $node->level;
                $record->is_root = $node->root;
                $record->version = $request->version;
                $record->save();
                $nodeAutoIdc[$node->localId] = $record->id;
            }
        }

        $allEdges = CoreEdge::all();

        foreach ($data->edges as $edge)
        {
            $fnode = null;
            $fnode = $allEdges->where('to_id', $nodeAutoIdc[$edge->target])->where('from_id',  $nodeAutoIdc[$edge->source])->first();    
            if (!empty($fnode))
            {
                $fnode->to_id = $nodeAutoIdc[$edge->target];
                $fnode->from_id = $nodeAutoIdc[$edge->source];
                $fnode->save();
        
            }
            else
            {
                $record = new CoreEdge();
                
                $record->to_id = $nodeAutoIdc[$edge->target];
                $record->from_id = $nodeAutoIdc[$edge->source];
                $record->save();
            }
        }

        echo "{ ok: true }";

    }
}