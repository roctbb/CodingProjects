@extends('layouts.left-menu')

@section('head')
<link rel="stylesheet" href="{{url('css/alchemy-white.css')}}"/>
<style>
    .alchemy text {
        display: block !important;
    }

    .alchemy g.active {
        opacity: 1;
    }

    g.use > circle {
        opacity: 1 !important;
        stroke: #fcff18 !important;
        stroke-opacity: 0.8;
        fill: green !important;
    }

    g.exists > circle {
        opacity: 0.8;

    }

    .alchemy > svg {
        background: white !important;
    }

    g.exists > text {
        fill: black;
        text-shadow: none;
    }

    g.use > text {
        fill: white !important;
        text-shadow: none;
    }
</style>


@endsection

@section('content')

    <div class="alchemy" id="alchemy" style="height:calc(88vh);"</div>

    <script src="{{url('js/vendor.js')}}"></script>
    <script src="{{url('js/alchemy.js')}}"></script>
    <script type="text/javascript">
        config = {
            directedEdges: true,
            fixRootNodes: true,
            dataSource: "{{url('/insider/core/network/'.$id.'?version='.$version)}}",
            nodeCaption: 'title',
            nodeClick: function (d) {
                edges = alchemy.getEdges(d.id);
                edges.forEach(function (edge) {

                    alchemy._edges[edge.id][0]._state='highlighted';
                    //alchemy._edges[edge.id][0]._style['opacity']='1';
                    alchemy._nodes[edge._properties.source]._state='highlighted';
                    //alchemy._nodes[edge._properties.source]._style['opacity']='1';
                    //console.log(alchemy._nodes[edge._properties.source]);

                    alchemy._edges[edge.id][0].setStyles()
                    alchemy._nodes[edge._properties.source].setStyles();
                })
            },

            cluster: true,
            alpha: 0,
            curvedEdges: true,
            forceLocked: false,
            nodeTypes: {"nodeType":["use", "exists"]},
            nodeStyle: {
                "all": {
                    "radius": function(d) {
                        return 200/d.getProperties().level;
                    }

                }
            },
            clusterColours: ["#1B9E77","#D95F02","#7570B3","#E7298A","#66A61E","#E6AB02", "#12AB02", "#99AB02"]
        };
        alchemy = new Alchemy(config);


    </script>
@endsection
