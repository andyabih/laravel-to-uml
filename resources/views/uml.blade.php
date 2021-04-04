<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Laravel to UML</title>
</head>
<body>
    <canvas id='canvas'></canvas>

    <script src="//unpkg.com/graphre/dist/graphre.js"></script>
    <script src="//unpkg.com/nomnoml/dist/nomnoml.js"></script>

    <script>
        var canvas = document.getElementById('canvas');
        var source = `{!! $source !!}`;
        nomnoml.draw(canvas, source);
    </script>
</body>
</html>