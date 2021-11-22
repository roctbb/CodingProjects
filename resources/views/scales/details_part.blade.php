<div class="row">
    <div class="col-12">
        <p>{{$scale->description}}</p>
        @foreach([[8, 'success'],[6, 'primary'],[4, 'info']] as $level)
            @foreach($scale->results->where('level', $level[0]) as $result)
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-1">
                                <h5><span class="badge badge-{{$level[1]}}">{{$level[0] / 2}}.0</span></h5>
                            </div>
                            <div class="col-11">
                                <h5 class="card-title lead">{{ $result->name }}</h5>
                                <p>{{ $result->description }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>
</div>
