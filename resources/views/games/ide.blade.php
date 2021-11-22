<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>Game IDE</title>
  <style>
  
  body {
    font-family: 'DejaVu Sans Mono', 'Consolas', monospace;
  }

  * {
    margin: 0;
    padding: 0;
  }
  #root
  {
    height: 100vh;
  }
  </style>

</head>
<body>
  <div id="root">
  <ide-app :game="{!! json_encode($game->id, JSON_HEX_TAG) !!}"></ide-app>
  </div>
  {{-- <script src="https://unpkg.com/monaco-editor@0.12.0/min/vs/loader.js"></script> --}}
  {{-- <script src="{{asset('javascript.js')}}"></script> --}}
<script src="{{asset('js/ide.js')}}"></script>
<script>
  window.blabla = 123;
</script>
  {{-- <script>
      require.config({ paths: { vs: "https://unpkg.com/monaco-editor@0.16.1/min/vs" } });
      require(["vs/editor/editor.main"], function() {
          window.editor = monaco.editor.create(document.getElementById("editor"), {
              value: {!! json_encode($game->code(), JSON_HEX_TAG) !!},
              language: "javascript",
              wordWrap: "on",
              theme: "vs-dark",
              automaticLayout: true
          });
      });
      function val() {
          return window.editor.getValue();
      }
  </script> --}}
</body>
</html>