// TODO: Each Object Type sohuld have its own class with "updateDom()" function, that will update DOM with changed new values

class MeshLogObject {
    constructor(meshlog, data) {
        this._meshlog = meshlog;
        this.data = {};
        this.flags = {};
        this.time = 0;
        this.highlight = false;
        this.merge(data);
    }

    merge(data) {
        // App shouldn't change data. It is updated on new advertisements
        this.data = {...this.data, ...data};
        this.time = new Date(data.created_at).getTime();
    }

    createDom(root) {}

    updateDom() {}

    update() {
        this.updateDom();
    }
}

class MeshLogReporter extends MeshLogObject {}
class MeshLogChannel extends MeshLogObject {}

class MeshLogContact extends MeshLogObject {
    constructor(meshlog, data) {
        super(meshlog, data);
        this.flags.dupe = false;
        this.hash = data.public_key.substr(0, 2).toLowerCase();
        this.messages = {};
    }

    addMessage(msg) {
        this.messages[msg.data.hash] = msg;
    }

    getAllMessages() {
        let grps = [];
        Object.entries(this._meshlog.messages).forEach(([_,grp]) => {
            let add = false;
            Object.entries(grp.messages).forEach(([_,msg]) => {
                if (msg.data.contact_id == this.data.id) {
                    add = true;
                }
            });
            if (add) { grps.push(grp); }
        });

        return grps;
    }

    showNeighbors() {
        const pathId = `${this.pathTag()}_${this.data.id}`;
        if (this._meshlog.map_layers.hasOwnProperty(pathId)) return;
        let links = {};

        // Works only for repeaters and ADV messages
        if (this.isRepeater() || this.isRoom()) {
            Object.entries(this._meshlog.messages).forEach(([_,grp]) => {
                Object.entries(grp.messages).forEach(([_,msg]) => {
                    if (!(msg instanceof MeshLogAdvertisement)) return;


                    if (msg.isExpired()) return;

                    let path = msg.data.path;
                    let parts = path.split(",");

                    const contact = this._meshlog.contacts[msg.data.contact_id];
                    const idx = parts.indexOf(this.hash);
                    let src = -1;
                    let dst = -1;


                    if (idx == 0) {
                        src = contact ? contact.data.public_key : msg.data.public_key;
                    } else if (idx > 0) {
                        src = parts[idx-1];
                    }

                    if (contact && (contact.isRepeater() || contact.isRoom())) {
                        if (contact.data.id == this.data.id) {
                            dst = parts[0];
                        }
                    }
                        
                    if (idx != -1) {
                        if ((idx + 1) < parts.length) {
                            dst = parts[idx+1];
                        }
                    }

                    if (src != -1) {
                        if (!links.hasOwnProperty(src)) {
                            links[src] = {
                                in: true,
                                out: false
                            };
                        } else {
                            links[src].in = true;
                        }
                    }

                    if (dst != -1) {
                        if (!links.hasOwnProperty(dst)) {
                            links[dst] = {
                                in: false,
                                out: true
                            };
                        } else {
                            links[dst].out = true;
                        }
                    }

                    // If parts is empty, add to incoming
                    // Add to incoming if path is empty (direct) or 
                });
            });
        }

        const _nocoord = function(c) {
            return (!c || c.length != 2 || (c[0] == 0 && c[1] == 0))
        };

        let layers = [];
        let src = [this.adv.data.lat, this.adv.data.lon];
        if (_nocoord(src)) return;

        const ln_weight = 2;
        const ln_outline = 4;
        const ln_offset = 3;

        Object.entries(links).forEach(([hash,dir]) => {
            let dst = this._meshlog.findContactByHash(hash);
            if (!dst) {
                console.log(`contact not found: ${dst}`)
                return
            }

            if (!dst.adv) {
                console.log(`contact ADV not found: ${dst}`)
                return
            }
            dst = [dst.adv.data.lat, dst.adv.data.lon];

            if (_nocoord(dst)) return;

            // Red is incoming
            if (dir.in) {
                layers.push(L.polyline([
                    src,
                    dst
                ], {color: 'white', weight: ln_outline}));

                layers.push(L.polyline([
                    src,
                    dst
                ], {color: '#F44336', weight: ln_weight}));
            }

            // Blue is outgoing
            if (dir.out) {
                let offset = dir.in ? ln_offset : 0;
                layers.push(L.polyline([
                    src,
                    dst
                ], {color: 'white', weight: ln_outline, offset: offset}));

                layers.push(L.polyline([
                    src,
                    dst
                ], {color: '#3949AB', weight: ln_weight, offset: offset}));
            }
        });

        let group = L.layerGroup(layers).addTo(this._meshlog.map);
        this._meshlog.map_layers[pathId] = group;

        return links;
    }

    hideNeighbors() {
        const pathId = `${this.pathTag()}_${this.data.id}`;
        if (!this._meshlog.map_layers.hasOwnProperty(pathId)) return;
        this._meshlog.map.removeLayer(this._meshlog.map_layers[pathId]);
        delete this._meshlog.map_layers[pathId];
    }

    getColor(str) {
        let hash = 0;
        for (let i = 0; i < this.data.name.length; i++) {
          hash = ((hash << 5) - hash) + this.data.name.charCodeAt(i);
          hash |= 0;
        }
        const threeByteHash = hash >>> 0 & 0xFFFFFF;
        return threeByteHash.toString(16).padStart(6, '0');
    }

    createDom(root) {
        if (this.dom) return this.dom.container;

        let container = document.createElement("div");

        let group = document.createElement("div");
        group.classList.add("log-entry");

        let name = document.createElement("span");
        name.classList.add("sp");
        name.classList.add("t");

        let date = document.createElement("span");
        date.classList.add("sp");
        date.classList.add("c");

        let hash = document.createElement("span");
        hash.classList.add("sp");

        let icon = document.createElement("img");
        icon.classList.add("ti");


        let details = document.createElement("div");

        let type = document.createElement("div");
        type.classList.add("sp");

        let pubkey = document.createElement("div");
        pubkey.classList.add("sp");
        pubkey.style.wordBreak = 'break-all';


        group.appendChild(date);
        group.appendChild(icon);
        group.appendChild(hash);
        group.appendChild(name);

        details.appendChild(type);
        details.appendChild(pubkey);
        details.hidden = true;

        container.appendChild(group);
        container.appendChild(details);

        const self = this;

        group.onclick = (e) => {
            const id = self.data.id;
            if (!self._meshlog.visible_contacts.hasOwnProperty(id)) {
                self.highlight = true;
                self._meshlog.visible_contacts[id] = 1;
                self.dom.details.hidden = false;
            } else {
                self.highlight = false;
                self.dom.details.hidden = true;
                delete self._meshlog.visible_contacts[id];
            }
            self.updateDom();
            self._meshlog.update();
        }

        group.onmouseover = (e) => {
            // Highligt messages
            // Draw adv travels
            
            Object.entries(this.messages).forEach(([k,msg]) => {
                msg.highlight = true;
                msg.updateDom();
            });

            this.showNeighbors();
            this._meshlog.visible_markers = [
                this.marker
            ];
            this._meshlog.fadeMarkers();
        }

        group.onmouseleave = (e) => {
            Object.entries(this.messages).forEach(([k,msg]) => {
                msg.highlight = false;
                msg.update();
            });

            this.hideNeighbors();
            this._meshlog.visible_markers = [];
            this._meshlog.fadeMarkers();
        }

        this.dom = {
            container,
            name,
            date,
            hash,
            icon,
            details,
            type,
            pubkey
        };

        if (root) root.appendChild(container);

        return container;
    }

    addToMap(map) {
        if (this.marker) return;
        this.map = map;

        if (!this.adv || (this.adv.data.lat == 0 && this.adv.data.lon == 0)) {
            return
        }

        let iconUrl = 'assets/img/tower.svg';
        let kl = 'marker-pin';
        let receipt = false;

        if (this.isClient()) {
            const rep = this.isReporter();
            if (rep) {
                receipt = rep.data.color;
            } else {
                iconUrl = 'assets/img/person.svg';
            }
        } else if (this.isRepeater()) {
            iconUrl = 'assets/img/tower.svg';
        } else if (this.isRoom()) {
            iconUrl = 'assets/img/group.svg';
        } else {
            iconUrl = 'assets/img/unknown.svg';
        }

        const extractEmoji = (str) => {
            const emojiRegex = /\p{Extended_Pictographic}/u;
            const match = str.match(emojiRegex);
            return match ? match[0] : '';
        }

        let innerIcon;
        let emoji = extractEmoji(this.adv.data.name);
        if (emoji) {
            innerIcon = document.createElement('span');
            innerIcon.innerText = emoji;
        } else if (receipt) {
            const hw = '20px';
            innerIcon = document.createElement('span');
            innerIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" height="${hw}" viewBox="0 -960 960 960" width="${hw}" fill="${receipt}"><path d="M240-80q-50 0-85-35t-35-85v-120h120v-560l60 60 60-60 60 60 60-60 60 60 60-60 60 60 60-60 60 60 60-60v680q0 50-35 85t-85 35H240Zm480-80q17 0 28.5-11.5T760-200v-560H320v440h360v120q0 17 11.5 28.5T720-160ZM360-600v-80h240v80H360Zm0 120v-80h240v80H360Zm320-120q-17 0-28.5-11.5T640-640q0-17 11.5-28.5T680-680q17 0 28.5 11.5T720-640q0 17-11.5 28.5T680-600Zm0 120q-17 0-28.5-11.5T640-520q0-17 11.5-28.5T680-560q17 0 28.5 11.5T720-520q0 17-11.5 28.5T680-480ZM240-160h360v-80H200v40q0 17 11.5 28.5T240-160Zm-40 0v-80 80Z"/></svg>`;
        } else {
            innerIcon = document.createElement('img');
            innerIcon.src = iconUrl;
        }

        let icdivroot = document.createElement("div");
        let icdivch1 = document.createElement("div");
        icdivch1.classList.add(kl);
        icdivroot.appendChild(icdivch1);
        icdivroot.appendChild(innerIcon);

        innerIcon.classList.add('marker-icon-img');

        if (!this.isClient()) {
            if (this.adv.isVeryExpired()) {
                icdivch1.classList.add("missing");
            } else if (this.adv.isExpired()) {
                icdivch1.classList.add("ghosted");
            }
        }

        let icon = L.divIcon({
            className: 'custom-div-icon',
            html: icdivroot,
            iconSize: [30, 42],
            iconAnchor: [15, 42]
        });

        const self = this;

        let tooltip = `<p class="tooltip-title">${this.adv.data.name}</p><p class="tooltip-detail">Last adv: ${this.adv.data.sent_at}</p>`;

        this.marker = L.marker([this.adv.data.lat, this.adv.data.lon], { icon: icon }).addTo(map);
        this.marker.bindTooltip(tooltip);
        this.marker.on('mouseover', (e) => {
            self.highlight = 'yellow';
            this.updateDom();
        });
        this.marker.on('mouseout', (e) => {
            self.highlight = '';
            this.updateDom();
        });
    }

    updateDom() {
        if (!this.dom) return;
        if (!this.adv) return;

        let hashstr = this.data.public_key.substr(0,2);

        if (this.adv.isExpired()) { // 3 days
            this.dom.date.classList.add("prio-6");
        } else {
            this.dom.date.classList.remove("prio-6");
        }

        if (this.flags.dupe) {
            this.dom.hash.classList.add("prio-5");
        } if (this.isRepeater()) {
            this.dom.hash.classList.add("prio-4");
        } else {
            this.dom.hash.classList.remove("prio-4");
            this.dom.hash.classList.remove("prio-5");
        }

        this.dom.pubkey.innerText = `Public Key: ${this.data.public_key}`;
        this.dom.container.dataset.type = this.adv.data.type;

        if (this.adv.data.type == 1) {
            this.dom.icon.src = "assets/img/person.svg";
            this.dom.type.innerText = `Type: Chat`;
        } else if (this.adv.data.type == 2) {
            this.dom.icon.src = "assets/img/tower.svg";
            this.dom.type.innerText = `Type: Repeater`;
        } else if (this.adv.data.type == 3) {
            this.dom.icon.src = "assets/img/group.svg";
            this.dom.type.innerText = `Type: Room`;
        } else {
            this.dom.type.innerText = `Type: Unknown`;
            this.dom.icon.src = "assets/img/unknown.svg";
        }

        this.dom.name.innerText = this.adv.data.name;
        this.dom.date.innerText = this.adv.data.sent_at;
        this.dom.hash.innerText = `[${hashstr}]`;

        const removeEmojis = (str) => {
            return str.replace(
                /([\u2700-\u27BF]|[\uE000-\uF8FF]|\uD83C[\uDC00-\uDFFF]|\uD83D[\uDC00-\uDFFF]|\uD83E[\uDD00-\uDFFF])/g,
                ''
            );
        };

        if (this.highlight) {
            this.dom.name.classList.add("chighlight");
        } else {
            this.dom.name.classList.remove("chighlight");
        }

        this.dom.container.dataset.time = this.adv.time;
        this.dom.container.dataset.name = removeEmojis(this.adv.data.name).trim();
        this.dom.container.dataset.hash = hashstr;
    }

    updateMarker() {
        if (!this.marker) return;
    }

    update() {
        this.updateDom();
        this.updateMarker();
    }

    isClient() {
        return this.adv && this.adv.data.type == 1;
    }

    isRepeater() {
        return this.adv && this.adv.data.type == 2;
    }

    isRoom() {
        return this.adv && this.adv.data.type == 3;
    }

    isReporter() {
        return this._meshlog.isReporter(this.data.public_key);
    }

    pathTag() { return 'c'; }
}

class MeshLogGroupChild extends MeshLogObject {
    createDom(root) {
        if (this.dom) return this.dom.container;

        let container = document.createElement("div");
        container.classList.add("log-entry");
        container.style.marginLeft = '4px';

        let date = document.createElement("span");
        date.classList.add("sp");
        date.classList.add("c");

        let text = document.createElement("span");
        text.classList.add("sp");

        let dot = document.createElement("span");
        dot.classList.add('dot');

        let reporter = this._meshlog.reporters[this.data.reporter_id];
        if (reporter) {
            dot.style.background = reporter.data.color;
        }

        container.appendChild(date);
        container.appendChild(dot);
        container.appendChild(text);

        const pathId = `${this.pathTag()}_${this.data.id}`;

        const self = this;
        container.onmouseover = (e) => {
            // show path
            if (self.parent.dom.pin.checked) return;
            let src = self._meshlog.contacts[self.data.contact_id];
            let dst = self._meshlog.reporters[self.data.reporter_id];
            self._meshlog.showPath(pathId, self.data.path, src, dst, this.color);
        }

        container.onmouseout = (e) => {
            // hide path
            if (self.parent.dom.pin.checked) return;
            self._meshlog.hidePath(pathId, self.data.id);
        }

        root.appendChild(container);
        
        this.dom = {
            container,
            date,
            text
        };
        
        return container;
    }

    updateDom() {
        if (!this.dom) return;
        this.dom.date.innerText = this.data.sent_at;
        this.dom.text.innerText = this.data.path ? this.data.path : 'direct';
    }

    pathTag() { return '?'; }
}

class MeshLogChannelMessage extends MeshLogGroupChild {
    pathTag() { return 'g'; }
}
class MeshLogDirecMessage extends MeshLogGroupChild {
    pathTag() { return 'd'; }
}
class MeshLogAdvertisement extends MeshLogGroupChild {
    pathTag() { return 'a'; }

    isExpired() {
        let now = new Date();
        let seen = new Date(this.data.sent_at);

        let age = now.getTime() - seen.getTime();
        return age > (3 * 24 * 60 * 60 * 1000);
    }

    isVeryExpired() {
        let now = new Date();
        let seen = new Date(this.data.sent_at);

        let age = now.getTime() - seen.getTime();
        return age > (7 * 24 * 60 * 60 * 1000);
    }
}

class MeshLogMessageGroup extends MeshLogObject {
    constructor(meshlog, data) {
        super(meshlog, data);
        this.messages = {};
    }

    addMessage(msg) {
        const colors = [
            "#F44336",
            "#8E24AA",
            "#3949AB",
            "#00897B",
            "#43A047",
            "#EF6C00",
        ];

        msg.parent = this;
        msg.color = colors[msg.data.id % colors.length];
        this.messages[msg.data.id] = msg;

        if (this.dom && !msg.dom) { 
            msg.createDom(this.dom.child);
        }
    }

    createDom(root) {
        const msg = this.first();
        if (!msg) return undefined;

        if (this.dom) return this.dom.container;

        let container = document.createElement("div");

        let group = document.createElement("div");
        group.classList.add("log-entry");

        let date = document.createElement("span");
        date.classList.add("sp");
        date.classList.add("c");

        let message = document.createElement("div");

        let name = document.createElement("span");
        name.classList.add("sp");
        name.classList.add("t");

        let text = document.createElement("span");
        text.classList.add("sp");

        let right = document.createElement("span");
        right.style.marginLeft= 'auto';
        right.style.whiteSpace = 'nowrap';

        let count = document.createElement("span");
        count.classList.add("sp");

        let pin = document.createElement("input");
        pin.type = 'checkbox';
        right.appendChild(pin);

        pin.onclick = (e) => {
            e.stopPropagation();
        }

        let child = document.createElement("div");
        child.style.borderLeft = "solid 2px #888";
        child.style.marginLeft = "2px";
        child.hidden = true;

        Object.entries(this.messages).forEach(([k,v]) => {
            v.createDom(child);
        });

        message.appendChild(name);
        message.appendChild(text);

        group.appendChild(date);
        group.appendChild(message);
        group.appendChild(right);
        container.appendChild(group);
        container.appendChild(child);

        this.dom = {
            container,
            group,
            name,
            date,
            text,
            right,
            count,
            pin,
            child
        };

        group.onclick = (e) => {
            child.hidden = !child.hidden;
        }

        group.onmouseover = (e) => {
            Object.entries(this.messages).forEach(([k,v]) => {
                v.dom.container.onmouseover(e);
            });
        }

        group.onmouseout = (e) => {
            Object.entries(this.messages).forEach(([k,v]) => {
                if (!pin.checked) {
                    v.dom.container.onmouseout(e);
                }
            });
        }

        let ins = false;
        container.dataset.time = msg.time;
        // augšā lielāks ID
        if (root) {
            var children = root.children;
            for (let i=0;i<children.length;i++) {
                let c = root.children[i];
                if (c.dataset.time < msg.time) {
                    root.insertBefore(container, c);
                    ins = true;
                    break;
                }
            }

            if (!ins) root.appendChild(container);
        }

        return container;
    }

    first() {
        const k = Object.keys(this.messages)[0];
        return this.messages[k];
    }

    size() {
        return Object.keys(this.messages).length;
    }

    updateDom() {
        let msg = this.first();
        if (!msg) return;

        this.dom.date.innerText = msg.data.sent_at;
        this.dom.name.innerText = msg.data.name + ": ";

        const sz = this.size();
        this.dom.count.innerText = `×${sz}`;

        let hidden = false;

        if (msg instanceof MeshLogAdvertisement) {
            this.dom.text.innerText = "Advert";
            this.dom.text.style.color = 'gray';
            hidden = !this._meshlog.settings.types.advertisements;
        } else if (msg instanceof MeshLogChannelMessage) {
            this.dom.text.innerText = msg.data.message;
            this.dom.name.style.color = '#d87dff'
            this.dom.text.style.color = 'white';
            hidden = !this._meshlog.settings.types.channel_messages;
        } else if (msg instanceof MeshLogDirecMessage) {
            this.dom.text.innerText = msg.data.message;
            this.dom.text.style.color = 'white';
            hidden = !this._meshlog.settings.types.direct_messages;
        } else {
            console.log("unkn instance");
            // ????
        }

        let allvis = Object.keys(this._meshlog.visible_contacts).length < 1;
        if (allvis || this._meshlog.visible_contacts.hasOwnProperty(msg.data.contact_id)) {
            this.dom.container.hidden = hidden | false;
        } else {
            this.dom.container.hidden = true;
        }

        if (this.highlight) {
            this.dom.group.classList.add("highlight");
        } else {
            this.dom.group.classList.remove("highlight");
        }

        Object.entries(this.messages).forEach(([k,v]) => {
            v.updateDom();
        })
    }
}

class MeshLog {
    constructor(map, logsid, contactsid, stypesid, sreportersid, scontactsid, errorid) {
        this.reporters = {};
        this.contacts = {};
        this.advertisements = {};
        this.channels = {};
        this.channel_messages = {};
        this.direct_messages = {};

        this.messages = {};

        this.map = map;
        this.map_layers = {};
        this.visible_markers = [];
        this.visible_contacts = {};
        this.link_pairs = {};
        this.dom_logs = document.getElementById(logsid);
        this.dom_contacts = document.getElementById(contactsid);
        this.dom_error = document.getElementById(errorid);
        this.timer = false;
        this.autorefresh = 0;

        // epoch of newest object
        this.latest = 0;
        this.window_active = true;
        this.new_messages = {};

        const self = this;

        window.onfocus = function () {
            self.window_active = true;
            self.clearNotifications();
        };

         
         window.onblur = function () {
            self.window_active = false;
         };
         

        // Settings objects
        this.settings = {
            types: {
                advertisements: true,
                channel_messages: true,
                direct_messages: false,
            },
            contactTypes: {
                repeaters: true,
                clients: true,
                rooms: true,
            },
            reporters: {

            },
            contacts: {

            }
        }

        this.dom_settings_types = document.getElementById(stypesid);
        this.dom_settings_reporters = document.getElementById(sreportersid);
        this.dom_settings_contacts = document.getElementById(scontactsid);

        this.__init_message_types();
        this.__init_contact_order();
        this.__init_contact_types();

        this.last = '2025-01-01 00:00:00';
    }

    __createCb(label, img, checked, onchange) {
        let div = document.createElement("div");
        let cb = document.createElement("input");
        let lbl = document.createElement("label");
        let ico = document.createElement("img");

        cb.type = "checkbox";
        cb.checked = checked;
        cb.onchange = onchange;

        lbl.innerText = label;

        if (img) {
            ico.src = img;
            ico.classList.add('icon-16');
            lbl.prepend(ico);
        }

        lbl.insertBefore(cb, lbl.firstChild);

        div.classList.add("settings-cb");
        div.appendChild(lbl);

        return div;
    }

    __onTypesChanged() {
        console.log(this.settings.types);
        this.update();
    }

    __onReportersChanged() {
        console.log(this.reporters);
        //this.updateReporters();
    }

    sortContacts(fn=undefined, reverse=false) {
        if (!fn) {
            fn = this.order.fn;
            reverse = this.order.reverse;
        }

        const items = Array.from(this.dom_contacts.children);
        items.sort(fn);
        if (reverse) items.reverse();
        items.forEach(item => {
            let type = parseInt(item.dataset.type);
            let hidden = false;;
            if (type == 1 && !this.settings.contactTypes.clients) { hidden = true; }
            else if (type == 2 && !this.settings.contactTypes.repeaters) { hidden = true; }
            else if (type == 3 && !this.settings.contactTypes.rooms) { hidden = true; }
            item.hidden = hidden;
            this.dom_contacts.appendChild(item)
        });
    }

    __init_contact_order() {
        let orders = [
            {
                name: 'Last Advert',
                fn: (a, b) => { 
                    return (Number(b.dataset.time) - Number(a.dataset.time));
                }
            },
            {
                name: 'Hash',
                fn: (a, b) => { 
                    return parseInt(`0x${a.dataset.hash}`) - parseInt(`0x${b.dataset.hash}`);
                }
            },
            {
                name: 'Name',
                fn: (a, b) => { 
                    return a.dataset.name.localeCompare(b.dataset.name);
                },
            },
        ];

        this.order = {
            fn: orders[0].fn,
            reverse: false,
            buttons: []
        };

        let container = document.createElement('div');
        let text = document.createElement('span');
        container.append(text);

        const self = this;
        for (let i=0;i<orders.length;i++) {
            let btn = document.createElement('button');
            btn.classList.add('btn');
            btn.innerText = orders[i].name;

            if (i == 0) {
                btn.classList.add('active');
            }

            btn.onclick = (e) => {
                for (const b of self.order.buttons) {
                    b.classList.remove('active');
                    b.classList.remove('reverse');
                }

                btn.classList.add('active');

                if (self.order.fn == orders[i].fn) {
                    self.order.reverse = !self.order.reverse;
                    if (self.order.reverse) {
                        btn.classList.add('reverse');
                    }
                } else {
                    self.order.fn = orders[i].fn;
                    self.order.reverse = false;
                }
                self.sortContacts();
            }
            this.order.buttons.push(btn);
            container.appendChild(btn);
        }

        this.dom_settings_contacts.appendChild(container);
    }

    __init_contact_types() {
        const self = this;
        this.dom_settings_contacts.appendChild(
            this.__createCb(
                "",
                "assets/img/tower.svg",
                this.settings.contactTypes.repeaters,
                (e) => {
                    this.settings.contactTypes.repeaters = e.target.checked;
                    self.sortContacts();
                }
            )
        );
        this.dom_settings_contacts.appendChild(
            this.__createCb(
                "",
                "assets/img/person.svg",
                this.settings.contactTypes.clients,
                (e) => {
                    this.settings.contactTypes.clients = e.target.checked;
                    self.sortContacts();
                }
            )
        );
        this.dom_settings_contacts.appendChild(
            this.__createCb(
                "",
                "assets/img/group.svg",
                this.settings.contactTypes.rooms,
                (e) => {
                    this.settings.contactTypes.rooms = e.target.checked;
                    self.sortContacts();
                }
            )
        );
    }

    __init_message_types() {
        const self = this;
        this.dom_settings_types.appendChild(
            this.__createCb(
                "Advertisements",
                "assets/img/beacon.png",
                this.settings.types.advertisements,
                (e) => {
                    this.settings.types.advertisements = e.target.checked;
                    self.__onTypesChanged(e);
                }
            )
        );

        this.dom_settings_types.appendChild(
            this.__createCb(
                "Channel Messages",
                "assets/img/message.png",
                this.settings.types.channel_messages,
                (e) => {
                    this.settings.types.channel_messages = e.target.checked;
                    self.__onTypesChanged(e);
                }
            )
        );

        this.dom_settings_types.append(
            this.__createCb(
                "Direct Messages",
                "assets/img/message.png",
                this.settings.types.direct_messages,
                (e) => {
                    this.settings.types.direct_messages = e.target.checked;
                    self.__onTypesChanged(e);
                }
            )
        );

        this.settings.notifications = false;
        this.dom_settings_types.append(
            this.__createCb(
                "🐐",
                "",
                this.settings.notifications,
                (e) => {
                    this.settings.notifications = e.target.checked;
                }
            )
        );
    }

    __init_reporters() {
        Object.entries(this.reporters).forEach(([id,_]) => {
            let reporter = this.reporters[id];
            if (reporter.hasOwnProperty('dom')) {
                return;
            }
            const self = this;
            this.reporters[id].enabled = true;
            this.dom_settings_reporters.hidden = true;
            // this.reporters[id].dom = this.dom_settings_reporters.appendChild(
            //     this.__createCb(
            //         this.reporters[id].data.name,
            //         false,
            //         this.reporters[id].enabled,
            //         (e) => {
            //             self.reporters[id].enabled = e.target.checked;
            //             self.__onReportersChanged(e)
            //         }
            //     )
            // );
        });
    }

    __init_contacts() {
        // Add sorters:
        //   By Date
        //   By Name
        // Add Display settings:
        //   Show names
        // Add some filters?
    }

    __addObject(dataset, id, obj) {
        if (dataset.hasOwnProperty(id)) {
            dataset[id].merge(obj.data);
        } else {
            dataset[id] = obj;
        }
    }

    __formatedTimestamp(d=new Date()) {
        const date = d.toISOString().split('T')[0];
        const time = d.toTimeString().split(' ')[0];
        return `${date} ${time}`
    }

    __prepareQuery(params={}) {
        let query = {};
        // bax date
        if (params.hasOwnProperty('after_ms')) {
            query.after_ms = params['after_ms'];
        }
        // min date
        if (params.hasOwnProperty('before_ms')) {
            query.before_ms = params['before_ms'];
        }

        // max count
        if (params.hasOwnProperty('count')) {
            query.before = params['count'];
        }

        // reporter ids
        if (params.hasOwnProperty('reporters')) {
            query.reporters = params['reporters'];
        }

        return query;
    }

    __fetchQuery(params, url, onResponse) {
        const query = this.__prepareQuery(params);
        const urlparams = new URLSearchParams();

        for (const key in query) {
            if (query.hasOwnProperty(key)) {
                const value = query[key];
                if (Array.isArray(value)) {
                    // For arrays, append each item with same key
                    value.forEach(item => urlparams.append(key, item));
                } else if (value !== undefined && value !== null) {
                    urlparams.append(key, value);
                }
            }
        }

        fetch(`${url}?${urlparams.toString()}`)
            .then(response => response.json())
            .then(data => onResponse(data));
    }

    showError(message, timeout=0) {
        this.setAutorefresh(0);
        this.dom_error.innerHTML = message;
        this.dom_error.classList.add('show');

        // Auto-hide after duration
        if (timeout > 0) {
           setTimeout(() => {
                errorBar.classList.remove('show');
            }, timeout);
        }
    }

    __loadObjects(dataset, data, klass) {
        if (data.error) {
            this.showError(data.error);
            return 0;
        }

        for (let i=0;i<data.objects.length;i++) {
            const o = data.objects[i];
            const id = o.id;
            const obj = new klass(this, o);
            this.__addObject(dataset, id, obj);

            if (o.created_at) {
                let created_at = new Date(o.created_at).getTime();
                if (created_at != 0) {
                    if (created_at > this.latest) {
                        this.latest = created_at;
                    }
                }
            }
        }

        return data.objects;
    }

    loadNew(onload=null) {
        let params = { 
            "after_ms": this.latest
        };
        this.loadAll(params, onload);
    }

    loadOld(onload=null) {
        const self = this;
        let oldest_adv = this.latest;
        let oldest_grp = this.latest;
        let oldest_dm  = this.latest;

        Object.entries(this.advertisements).forEach(([k,v]) => {
            let created_at = new Date(v.data.created_at).getTime();
            oldest_adv = Math.min(oldest_adv, created_at);
        });

        Object.entries(this.channel_messages).forEach(([k,v]) => {
            let created_at = new Date(v.data.created_at).getTime();
            oldest_grp = Math.min(oldest_grp, created_at);
        });

        Object.entries(this.direct_messages).forEach(([k,v]) => {
            let created_at = new Date(v.data.created_at).getTime();
            oldest_dm = Math.min(oldest_dm, created_at);
        });

        this.__fetchQuery({ "before_ms": oldest_adv }, 'api/v1/advertisements', data => {
            const rep = self.__loadObjects(self.advertisements, data, MeshLogAdvertisement);
            if (rep.length) console.log(`${rep.length} advertisements loaded`);
            self.onLoadAll();
            if (onload) onload();
        });

        this.__fetchQuery({ "before_ms": oldest_grp }, 'api/v1/channel_messages', data => {
            const rep = self.__loadObjects(self.channel_messages, data, MeshLogChannelMessage);
            if (rep.length) console.log(`${rep.length} group messages loaded`);
            self.onLoadAll();
            if (onload) onload();
        });

        this.__fetchQuery({ "before_ms": oldest_dm }, 'api/v1/direct_messages', data => {
            const rep = self.__loadObjects(self.direct_messages, data, MeshLogDirecMessage);
            if (rep.length) console.log(`${rep.length} direct messages loaded`);
            self.onLoadAll();
            if (onload) onload();
        });
    }

    loadAll(params={}, onload=null) {
        this.__fetchQuery(params, 'api/v1/all', data => {
            if (data.error) {
                this.showError(data.error);
                return;
            }

            const rep1 = this.__loadObjects(this.reporters, data.reporters, MeshLogReporter);
            const rep2 = this.__loadObjects(this.contacts, data.contacts, MeshLogContact);
            const rep4 = this.__loadObjects(this.channels, data.channels, MeshLogChannel);

            const rep3 = this.__loadObjects(this.advertisements, data.advertisements, MeshLogAdvertisement);
            const rep5 = this.__loadObjects(this.channel_messages, data.channel_messages, MeshLogChannelMessage);
            const rep6 = this.__loadObjects(this.direct_messages, data.direct_messages, MeshLogDirecMessage);

            if (rep1.length) console.log(`${rep1.length} reporters loaded`);
            if (rep2.length) console.log(`${rep2.length} contacts loaded`);
            if (rep3.length) console.log(`${rep3.length} advertisements loaded`);
            if (rep4.length) console.log(`${rep4.length} groups loaded`);
            if (rep5.length) console.log(`${rep5.length} group messages loaded`);
            if (rep6.length) console.log(`${rep6.length} direct messages loaded`);

            this.__init_reporters();
            this.onLoadAll();

            if (onload) {
                onload({
                    reporters: rep1,
                    contacts: rep2,
                    groups: rep4,
                    advertisements: rep3,
                    channel_messages: rep5,
                    direct_messages: rep6,
                });
            }
        });
    }

    onLoadContacts() {
        let hashes = {};
        Object.entries(this.contacts).forEach(([id,contact]) => {
            let adv = Object.values(this.advertisements).reverse().find(item => item.data.contact_id == id);

            if (!adv && contact.data.advertisement) {
                adv = new MeshLogAdvertisement(this, contact.data.advertisement);
            }
            
            if (!adv) return;

            contact.adv = adv;

            let hashstr = contact.data.public_key.substr(0,2);

            // Mark dupes
            if (hashes.hasOwnProperty(hashstr) && contact.isRepeater()) {
                for (let i=0;i<hashes[hashstr].length;i++) {
                    hashes[hashstr][i].flags.dupe = true;
                    hashes[hashstr][i].updateDom();
                }
                contact.flags.dupe = true;
            } else {
                hashes[hashstr] = [];
            }
            hashes[hashstr].push(contact);

            contact.createDom(this.dom_contacts);
            contact.addToMap(this.map);
            contact.update();
        });
        this.sortContacts();
    }

    addMessage(msg) {
        // identified by date + hash
        const hash = msg.data.hash;
        if (!this.messages.hasOwnProperty(hash)) {
            this.onNewMessage(msg);
            this.messages[hash] = new MeshLogMessageGroup(this, {
                sent_at: msg.data.sent_at,
                created_at: msg.data.created_at, // this will be different for each message, but it helps for
            });
        }
        this.messages[hash].addMessage(msg);

        let contact = this.contacts[msg.data.contact_id];
        if (contact) this.contacts[msg.data.contact_id].addMessage(this.messages[hash]);
    }

    findContactByHash(hash) {
        let pk = hash.length > 4;
        for (const [_, contact] of Object.entries(this.contacts)) {
            if (pk && contact.data.public_key == hash) {
                return contact;
            } else if (contact.hash == hash) {
                return contact;
            }
        }
        return undefined;
    }

    update() {
        for (const [key, msg] of Object.entries(this.messages)) {
            msg.createDom(this.dom_logs);
            msg.update();
        }
    }

    onLoadMessages() {
        Object.entries(this.advertisements).forEach(([id,_]) => { this.addMessage(this.advertisements[id]); });
        Object.entries(this.channel_messages).forEach(([id,_]) => { this.addMessage(this.channel_messages[id]); });
        Object.entries(this.direct_messages).forEach(([id,_]) => { this.addMessage(this.direct_messages[id]); });

        this.update();
    }

    onLoadAll() {
        this.onLoadContacts();
        this.onLoadMessages();
    }

    loadReporters(params={}, onload=null) {
        this.__fetchQuery(params, 'api/v1/reporters', data => {
            const sz = this.__loadObjects(this.reporters, data, MeshLogObject);
            console.log(`${sz} reporters loaded`);
            if (onload) onload();
        });
    }

    loadContacts(params={}, onload=null) {
        this.__fetchQuery(params, 'api/v1/contacts', data => {
            const sz = this.__loadObjects(this.contacts, data, MeshLogContact);
            console.log(`${sz} contacts loaded`);
            if (onload) onload();
        });
    }

    loadAdvertisements(params={}, onload=null) {
        this.__fetchQuery(params, 'api/v1/advertisements', data => {
            const sz = this.__loadObjects(this.advertisements, data, MeshLogAdvertisement);
            console.log(`${sz} advertisements loaded`);
            if (onload) onload();
        });
    }

    loadChannels(params={}, onload=null) {
        this.__fetchQuery(params, 'api/v1/channels', data => {
            const sz = this.__loadObjects(this.channels, data, MeshLogObject);
            console.log(`${sz} channels loaded`);
            if (onload) onload();
        });
    }

    loadChannelMessages(params={}, onload=null) {
        this.__fetchQuery(params, 'api/v1/channel_messages', data => {
            const sz = this.__loadObjects(this.channel_messages, data, MeshLogChannelMessage);
            console.log(`${sz} channels messages loaded`);
            if (onload) onload();
        });
    }

    loadDirectMessages(params={}, onload=null) {
        this.__fetchQuery(params, 'api/v1/direct_messages', data => {
            const sz = this.__loadObjects(this.direct_messages, data, MeshLogDirecMessage);
            console.log(`${sz} direct messages loaded`);
            if (onload) onload();
        });
    }

    fadeMarkers(opacity=0.2) {
        const empty = this.visible_markers.length == 0; 
        Object.entries(this.contacts).forEach(([k,v]) => {
            if (!v.marker) return;
            if (empty || this.visible_markers.includes(v.marker)) {
                v.marker.setOpacity(1);
                v.marker.setZIndexOffset(1000);
            } else {
                v.marker.setOpacity(opacity);
                v.marker.setZIndexOffset(2);
            }
        });
    }

    showPath(id, path, src, dst, color) {
        if (this.map_layers.hasOwnProperty(id)) return;

        // TODO: generate "link" pairs. If same node pair exists, place new layer under and with larger radius

        let layers = [];
        let last = [];
        let hashes = path ? path.split(',') : [];


        // Show end/dst on map. Sould be logger
        Object.entries(this.contacts).forEach(([k,v]) => {
            if (v.data.public_key == dst.data.public_key) {
                if (v.marker) {
                    this.visible_markers.push(v.marker);
                    this.map.removeLayer(v.marker);
                    v.marker.addTo(this.map);
                }
            }
        });

        // Show start/src on map
        if (!src || (src.adv && src.isClient())) {
            // If source is client, draw circle on first repeater
            if (hashes.length > 0) {
                Object.entries(this.contacts).forEach(([k,v]) => {
                    if (v.hash == hashes[0] && v.adv && !v.adv.isVeryExpired() && v.isRepeater()) {
                        if (v.adv.data.lat != 0 && v.adv.data.lon != 0) {
                            last.push([v.adv.data.lat, v.adv.data.lon]);
                            if (v.marker) {
                                this.visible_markers.push(v.marker);
                                this.map.removeLayer(v.marker);
                                v.marker.addTo(this.map);
                            }
                        }
                    }
                });
            }

            for (let i=0;i<last.length;i++) {
                let circle = L.circle(last[i], {
                    color: color,
                    fillColor: color,
                    fillOpacity: 0.2,
                    radius: 1000
                });
                layers.push(circle);
            }
        } else if (src.adv) {
            // Starts from node
            if (src.marker) {
                this.visible_markers.push(src.marker);
                this.map.removeLayer(src.marker);
                src.marker.addTo(this.map);
            }
            if (src.adv.data.lat != 0 && src.adv.data.lon != 0) {
                last.push([src.adv.data.lat, src.adv.data.lon]);
            }
        }

        const ln_weight = 2;
        const ln_outline = 4;
        const ln_offset = 3;
        const ln_max_offets = 6;

        let prev = [dst.data.lat, dst.data.lon];
        console.log(dst);;

        for (let i=hashes.length-1;i>=0;i--) {
            let hash = hashes[i];
            let matches = 0;
            let match = false;
            let matchDist = 99999;

            // Find nearest repeater with hash
            Object.entries(this.contacts).forEach(([k,c]) => {
                if (c.hash == hash && c.adv && !c.adv.isVeryExpired() && c.isRepeater()) {
                    let current = [c.adv.data.lat, c.adv.data.lon];
                    if (current[0] == 0 && current[1] == 0) return;

                    matches++;
                    const dist = haversineDistance(prev[0], prev[1], current.lat, current.lon);
                    if (!match || dist < matchDist) {
                        match = c;
                        matchDist = dist;
                    }
                }
            });

            if (matches > 1) {
                console.log(`Multiple paths (${matches}) detected to ${hash}. Showing shortest.`);
            }

            // Valid repeater found?
            if (match) {
                this.visible_markers.push(match.marker);
                this.map.removeLayer(match.marker);
                match.marker.addTo(this.map);
                let current = [match.adv.data.lat, match.adv.data.lon];
                let pair_id = `${prev[0]}-${prev[1]}_${current[0]}-${current[1]}`;

                if (!this.link_pairs.hasOwnProperty(pair_id)) {
                    this.link_pairs[pair_id] = 0;
                }

                let offset = Math.floor((this.link_pairs[pair_id] + 1) / 2) * ln_offset;
                if (offset > ln_max_offets) offset = 0;
                offset *= this.link_pairs[pair_id] % 2 == 0 ? 1 : -1;


                this.link_pairs[pair_id]++;

                layers.push(L.polyline([
                    prev,
                    current
                ], {color: 'white', weight: ln_outline, offset: offset}));

                layers.push(L.polyline([
                    prev,
                    current
                ], {color: color, weight: ln_weight, offset: offset}));

                prev = [match.adv.data.lat, match.adv.data.lon];
            }
        }

        let current = [dst.data.lat, dst.data.lon];
        for (let j=0;j<last.length;j++) {
            layers.push(L.polyline([
                last[j],
                current
            ], {color: 'white', weight: ln_outline}));

            layers.push(L.polyline([
                last[j],
                current
            ], {color: color, weight: ln_weight}));
        }

        let group = L.layerGroup(layers).addTo(this.map);
        this.map_layers[id] = group;
        this.fadeMarkers();
    }

    hidePath(id) {
        if (!this.map_layers.hasOwnProperty(id)) return;
        this.map.removeLayer(this.map_layers[id]);
        delete this.map_layers[id];
        this.visible_markers = [];
        this.link_pairs = {};
        this.fadeMarkers();
    }

    clearHighlights() {
        Object.entries(this.messages).forEach(([_,grp]) => {
            grp.highlight = false;
            Object.entries(grp.messages).forEach(([_,msg]) => {
                msg.highlight = false;
            });
        });
    }

    refresh() {
        clearTimeout(this.timer);
        const self = this;
        this.loadNew((data) => {
            const count = Object.keys(this.new_messages).length;
            if (count) {
                if (this.settings.notifications) {
                    new Audio('assets/audio/notif.mp3').play();
                }

                document.getElementById('favicon').setAttribute('href','assets/favicon/faviconr.ico');
                document.title = `(${count}) MeshCore Log`; 
            }
        });
        this.setAutorefresh(this.interval);
    }

    setAutorefresh(interval) {
        this.new_messages = {};
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }

        if (interval >= 5000) {
            this.interval = interval;
            const self = this;
            this.timer = setTimeout(() => { self.refresh(); }, interval);
        } else {
            this.interval = 0;
        }
    }

    onNewMessage(msg) {
        if (msg instanceof MeshLogChannelMessage || msg instanceof MeshLogDirecMessage) {
            const hash = msg.data.hash;
            if (!this.new_messages.hasOwnProperty(hash)) {
                this.new_messages[hash] = [];
            };
            this.new_messages[hash].push(msg);
        }
    }

    clearNotifications() {
        this.new_messages = [];
        document.getElementById('favicon').setAttribute('href','assets/favicon/faviconw.ico');
        document.title = `MeshCore Log`; 
    }

    showAllPaths() {
        Object.entries(this.messages).forEach(([k,v]) => {
            v.dom.group.onmouseover({});
        });
    }

    isReporter(public_key) {
        for (const key in this.reporters) {
            if (this.reporters[key].data.public_key == public_key) {
                return this.reporters[key];
            }
        }
        return false;
    }
}

function haversineDistance(lat1, lon1, lat2, lon2) {
    const toRad = (angle) => (angle * Math.PI) / 180;

    const R = 6371; // Radius of the Earth in km
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);

    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c; // Distance in km
}
