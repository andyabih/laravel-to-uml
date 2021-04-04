<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;700&display=swap" rel="stylesheet">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Laravel to UML</title>
    <style>
        html,
        body {
            min-height: 100vh;
            min-width: 100vh;
            background: #071013;
        }

        body {
            display: grid;
        }

        #canvas {
            display: table;
            margin: 0 auto;
            align-self: center;
        }
    </style>
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