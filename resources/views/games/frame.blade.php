<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{$game->title}}</title>
    <style>
        body {
            margin: 0;
            overflow: hidden;
            font-family: sans-serif;
        }

        #console {
            display: none;
            background: #fff;
            overflow: auto;
            position: fixed;
            bottom: 0;
            box-shadow: 0 -5px 100px rgba(0, 0, 0, 0.05);
            left: 0;
            right: 0;
            max-height: 150px;


            border-top: 1px solid #ccc
        }

        .err {
            color: crimson;
        }

        .log {
            color: #555;
        }

        .warn {
            color: #f7da65;
        }

        .msg {
            padding: 10px;
        }

        .info {
            color: teal;
        }

        canvas {
            width: 100vw;
            height: 100vh;
        }
    </style>
</head>

<body>
    <canvas id="cv"></canvas>
    <div id="console">
    </div>
    @if ($game->type == "canvas")
    <script>
        function htmlspecialchars(str) {
            return str.replace(/\</g, '&lt;')
                .replace(/\>/g, '&gt;')
                .replace(/\&/g, '&amp;');
        }

        function genmsg(l, t) {
            l = l.toString();
            document.getElementById('console').style.display = "block";
            document.getElementById('console').innerHTML += "<div class='msg " + t + "'>" + htmlspecialchars(l) +
                "</div>";
            document.getElementById('console').scrollTo(0, document.getElementById('console').scrollHeight);
        }
        // console.log = function(l)
        // {
        //     genmsg(l, 'log');
        // }

        // console.warn = function(l)
        // {
        //     genmsg(l, 'warn');
        // }

        // console.error = function(l)
        // {
        //     genmsg(l, 'err');
        // }

        // console.info = function(l)
        // {
        //     genmsg(l, 'info');
        // }

        var canvas = document.getElementById('cv');
        var context = canvas.getContext('2d');
        var dpr = window.devicePixelRatio || 1;
        console.log(dpr);
        canvas.width = window.innerWidth * dpr;
        canvas.height = window.innerHeight * dpr;
        context.scale(dpr, dpr);

        window.addEventListener('resize', function () {
            var dpr = window.devicePixelRatio || 1;
            canvas.width = window.innerWidth * dpr;
            canvas.height = window.innerHeight * dpr;
            context.scale(dpr, dpr);
        });

        context.clearColor = "black";

        

        context.clear = function () {
            var tempFill = context.fillStyle;
            context.fillStyle = context.clearColor;
            context.fillRect(0, 0, engine.width(), engine.height())
            context.fillStyle = tempFill;
        };


        document.cookie = null;
        if (!document.__defineGetter__) {
            Object.defineProperty(document, 'cookie', {
                get: function () {
                    return ''
                },
                set: function () {
                    return true
                },
            });
        } else {
            document.__defineGetter__("cookie", function () {
                return '';
            });
            document.__defineSetter__("cookie", function () {});
        }

        function update() {};

        var engine = {};

        engine.mouse_x = 0;
        engine.mouseButtons = {};
        engine.mouse_y = 0;
        engine.keys = {};
        engine.____fps____ = 60;
        engine.____ivnum____ = setInterval(update, 1000 / engine.____fps____);
        engine.width = function ()
        {
            var dpr = window.devicePixelRatio || 1;
            return canvas.width/dpr;
        }
        engine.height = function ()
        {
            var dpr = window.devicePixelRatio || 1;
            return canvas.height/dpr;
        }

        onkeydown = onkeyup = function (e) {
            e.preventDefault();
            e = e || event;
            engine.keys[e.key] = e.type == 'keydown';
        }

        onmouseup = onmousedown = function (e) {
            e = e || event;
            engine.mouseButtons[e.button] = e.type == 'mousedown';
        }

        engine.getMousePosition = function () {
            return {
                x: engine.mouse_x,
                y: engine.mouse_y
            };
        }

        canvas.addEventListener('mousemove', function (e) {
            engine.mouse_x = e.clientX;
            engine.mouse_y = e.clientY;
        });


        function fps(v) {
            if (v < 1) {
                v = 1;
            }
            if (v > 70) {
                v = 70;
            }
            engine.____fps____ = v;
            clearInterval(engine.____ivnum____);
            engine.____ivnum____ = setInterval(update, 1000 / engine.____fps____);
        }



        window.alert = null;
        window.comfirm = null;
        window.prompt = null;
    </script>
    @elseif ($game->type == "webgl")
    <script src="{{asset('/js/agl.js')}}"></script>
    @endif

    <script type="text/javascript" src="/insider/games/{{$game->id}}/viewsource"></script>
</body>

</html>