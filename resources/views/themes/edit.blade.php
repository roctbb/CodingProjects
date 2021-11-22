@extends('layouts.left-menu')

@section('title')
    GeekClass: Добавление проекта
@endsection

@section('head')
    <script src="/codemirror.js"></script>
    <script src="/css.js"></script>
    <script src="/javascript.js"></script>
    <link rel="stylesheet" href="/codemirror.css">
@endsection


@section('content')
    <h2>Создание проекта</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="form-horizontal" enctype="multipart/form-data">

                        <div class="form-group">
                            {{ csrf_field() }}
                            <label for="name">Название</label>
                            <input id="name" type="text" class="form-control" name="name" value="{{ null !== $theme->name ? $theme->name : old('name')}}"
                                   required>
                            @if ($errors->has('name'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-group">
                            {{ csrf_field() }}
                            <label for="price">Цена</label>
                            <input id="price" type="text" class="form-control" name="price" value="{{ null !== $theme->price ? $theme->price : old('price')}}"
                                  required>
                            @if ($errors->has('price'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('price') }}</strong>
                                    </span>
                            @endif
                        </div>
      
                        <div class="form-group">
                          {{ csrf_field() }}
                          <label for="image">Ссылка на картинку</label>
                          <input id="image" type="text" class="form-control" name="image" value="{{ null !== $theme->image ? $theme->image : old('image')}}"
                                required>
                          @if ($errors->has('image'))
                              <span class="help-block error-block">
                                      <strong>{{ $errors->first('image') }}</strong>
                                  </span>
                          @endif
                      </div>
      
                        <div class="form-group">
                            <label for="description">Описание</label>
                            <textarea id="description" class="form-control"
                                      name="description">{{ null !== $theme->description ? $theme->description : old('description')}}</textarea>
                            @if ($errors->has('description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>
            
                        
                        <div class="form-group">
                            <label for="css">CSS</label>
                            <textarea id="css" class="form-control" rows=20
                                      name="css">{{ null !== $theme->css() ? $theme->css() : old('css')}}</textarea>
                            @if ($errors->has('css'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('css') }}</strong>
                                    </span>
                            @endif
                        </div>
          
          
                        <div class="form-group">
                          <label for="js">JS</label>
                            <textarea id="js" class="form-control" rows=20
                                      name="js">{{ null !== $theme->js() ? $theme->js() : old('js')}}</textarea>
                            @if ($errors->has('js'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('js') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <input type="submit" class="btn btn-success" value="Изменить"/>
                    </form>
                </div>
            </div>
        </div>
        <script>
            var simplemde_description = new EasyMDE({
                spellChecker: false,
                autosave: true,
                element: document.getElementById("description")
            });
            var simplemde_text = new EasyMDE({
                spellChecker: false,
                autosave: true,
                element: document.getElementById("description")
            });
            CodeMirror.fromTextArea(document.getElementById("css"), {
                lineNumbers: true,
                mode:  "css"
            }).setSize(null, 500);
            CodeMirror.fromTextArea(document.getElementById("js"), {
                lineNumbers: true,
                mode:  "javascript"
            }).setSize(null, 500);
        </script>
    </div>
@endsection
