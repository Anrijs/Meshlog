<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link id="favicon" rel="icon" type="image/x-icon" href="assets/favicon/faviconw.ico">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>
    <script src=" https://cdn.jsdelivr.net/npm/leaflet-polylineoffset@1.1.1/leaflet.polylineoffset.min.js "></script>
    <script src="assets/js/meshlog.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <title>MeshCore Log v1.99</title>
</head>
<body>

<div id="error"></div>
<div id="container">
<div id="leftbar">
    <div class="settings" id="settings-types">
    </div>
    <div class="settings" id="settings-reporters">
    </div>
    <div id="logs"></div>
</div>
<div id="midbar">
    <div class="resize-bar" id="leftdrag"></div>
    <div id="map"></div>
    <div id="warning" hidden></div>
    <div class="resize-bar" id="rightdrag"></div>
</div>
<div id="rightbar">
    <div class="settings" id="settings-contacts">
    </div>
    <div class="settings" id="about">MeshLog Web v1.99</div>
    <div id="contacts"></div>
</div>
</div>
<script>

// resize bars

class Bar {
    constructor(id, width) {
        this.tmpWidth = undefined;
        this.dom = document.getElementById(id);
        this.setWidth(width);
    }

    setWidth(w) {
        this.width = w;
        this.dom.style.width = `${w}%`;
    }

    setTmpWidth(w) {
        if (this.tmpWidth == undefined) {
            this.tmpWidth = this.width;
            this.setWidth(w);
        }
    }

    resetWidth() {
        if (this.tmpWidth != undefined) {
            this.setWidth(this.tmpWidth);
            this.tmpWidth = undefined;
        }
    }
}

class Drags {
    constructor(id) {
        this.pairs = [];
        this.container = document.getElementById(id);

        self = this;

        this.container.addEventListener("mousemove", function (e) {
            e.preventDefault();

            let pair = undefined;
            for (var i=0;i<self.pairs.length;i++) {
                if (self.pairs[i].drag) {
                    pair = self.pairs[i];
                    break;
                }
            }

            if (!pair) return;

            let split = ( e.x - pair.x0) / pair.width;

            if (split < 0.05) split = 0.05;

            let ppLeft = pair.sum * split; // % left
            let ppRight = pair.sum - ppLeft;

            pair.left.setWidth(ppLeft);
            pair.right.setWidth(ppRight);
            
            map.invalidateSize();
        });

        this.container.addEventListener("mouseup", function (e) {
            self.cancelDrag();
        });
    }

    add(pair) {
        pair.bind(this);
        this.pairs.push(pair);
    }

    cancelDrag() {
        for (var i=0;i<this.pairs.length;i++) {
            this.pairs[i].drag = false;
        }
    }

    isDraging() {
        for (var i=0;i<this.pairs.length;i++) {
            if (this.pairs[i].drag) return true;
        }
        return false;
    }
}

class DragPair {
    constructor(id, left, right) {
        this.drag = false;
        this.left = left;
        this.right = right;
        this.bar = document.getElementById(id);
        this.calc();

        const self = this;

        this.bar.addEventListener("mousedown", function (e) {
            if (self.drags) self.drags.cancelDrag();
            self.calc();
            self.drag = true;
        });
    }

    calc() {
        this.x0 = this.left.dom.getBoundingClientRect().left;
        this.x1 = this.right.dom.getBoundingClientRect().right;
        this.width = this.x1 - this.x0;

        this.sum = this.percent2num(this.left.dom.style.width) + this.percent2num(this.right.dom.style.width);
    }

    percent2num(percent) {
        return parseFloat(percent.replaceAll("%","").trim());
    }

    bind(drags) {
        this.drags = drags;
    }
}

const leftBar = new Bar("leftbar", 33);
const middleBar = new Bar("midbar", 47);
const rightBar = new Bar("rightbar", 20);

const dragLeft = new DragPair("leftdrag", leftBar, middleBar);
const dragRight = new DragPair("rightdrag", middleBar, rightBar);

function resize() {
    if (window.innerWidth <= 900) {
        leftBar.setTmpWidth(100);
        middleBar.setTmpWidth(100);
        rightBar.setTmpWidth(100);
    } else {
        leftBar.resetWidth();
        middleBar.resetWidth();
        rightBar.resetWidth();
    }
}

window.addEventListener("resize", function() {
    resize();
});

const drags = new Drags("container");
drags.add(dragLeft);
drags.add(dragRight);

resize();

const formatedTimestamp = (d=new Date())=> {
  const date = d.toISOString().split('T')[0];
  const time = d.toTimeString().split(' ')[0];
  return `${date} ${time}`
}

var map = L.map('map').setView([56.96894, 24.14520], 10);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
}).addTo(map);

var meshlog = new MeshLog(
    map,
    "logs",
    "contacts",
    "settings-types",
    "settings-reporters",
    "settings-contacts",
    "warning",
    "error"
);
meshlog.loadAll();
meshlog.setAutorefresh(10000);

</script>
</body>
</html>
