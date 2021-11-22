        <script>
        function dislike(id, el) {
            el.attr('data-likes', parseInt(el.attr('data-likes')) - 1);
            var ch = el.children('img').attr('src', '{{ url('images/icons/icons8-heart-24.png') }}');
           
            ch.hide();
            ch.show(500);
            $.ajax({
                url: '/insider/events/'+id+'/dislike',
            });
            el.children('.likes1').html(el.attr('data-likes'));

        }
        function like(id, el) {
            el.attr('data-likes', parseInt(el.attr('data-likes')) + 1);
            
            var ch = el.children('img').attr('src', '{{ url('images/icons/icons8-like-48.png') }}');
            ch.hide();
            ch.show(500);
            $.ajax({
                url: '/insider/events/'+id+'/like',
            });

            el.children('.likes1').html(el.attr('data-likes'));

        }
        function toggleLike(id, el)
        {
            if (el.attr('data-liked') == 'true')
            {
                dislike(id, el);
                el.attr('data-liked', 'false');
            }
            else
            {
                like(id, el);
                el.attr('data-liked', 'true');
            }

        }
    </script>