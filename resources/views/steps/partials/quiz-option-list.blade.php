@if($options)
    <ol>
        @foreach($options as $option)
            <li>{{ $option['label'] }}</li>
        @endforeach
    </ol>
@endif
