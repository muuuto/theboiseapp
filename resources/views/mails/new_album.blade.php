<html>
    Hello there {{$name}}, <br><br>
    
    a new album - <strong>{{$albumTitle}}</strong> - is available inside THEBOISE. Please <a href="{{$albumLink}}">click here</a> to visit the album and leave a comment. <br><br>
    <img src="{{asset('storage/app/public/' . $albumLogo)}}" width="600" alt="" style="width: 100%; max-width: 600px; height: auto; border-radius: 8px;">

    <br>
    theboise team
</html>