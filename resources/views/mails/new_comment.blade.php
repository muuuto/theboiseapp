<html>
    Hello there {{$name}}, <br><br>

    a new comment was added to the <strong>{{$albumTitle}}</strong> album by <strong>{{$commentOfUser}}</strong>:

    <br>
    <i>{!! $comment !!}</i>
    <br>
    <br>

    Please <a href="{{$albumLink}}">visit</a> to view the album and answer the comment. <br>
    theboise team
</html>
