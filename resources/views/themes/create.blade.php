@extends('layouts.left-menu')

@section('title')
    GeekClass: Добавление проекта
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
                            <input id="name" type="text" class="form-control" name="name" value="{{old('name')}}"
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
                            <input id="price" type="text" class="form-control" name="price" value="{{old('price')}}"
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
                          <input id="image" type="text" class="form-control" name="image" value="{{old('image')}}"
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
                                      name="description">{{old('description')}}</textarea>
                            @if ($errors->has('description'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('description') }}</strong>
                                    </span>
                            @endif
                        </div>
            
                        
                        <div class="form-group">
                            <label for="css">CSS</label>
                            <textarea id="css" class="form-control" rows=20
                                      name="css">{{old('css')}}</textarea>
                            @if ($errors->has('css'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('css') }}</strong>
                                    </span>
                            @endif
                        </div>
          
          
                        <div class="form-group">
                          <label for="js">JS</label>
                            <textarea id="js" class="form-control" rows=20
                                      name="js">{{old('js')}}</textarea>
                            @if ($errors->has('js'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('js') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <input type="submit" class="btn btn-success" value="Создать"/>
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
        </script>
    </div>
@endsection
