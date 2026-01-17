class Settings {
    static get(key, def=undefined) {
        if (!localStorage.hasOwnProperty(key)) {
            localStorage[key] = def;
        }
        return localStorage[key];
    }

    static getBool(key, def=undefined) {
        if (!localStorage.hasOwnProperty(key)) {
            localStorage[key] = def;
        }
        return localStorage[key] === "true";
    }

    static set(key, value) {
        localStorage[key] = value;
    }
}

class MeshLogObject {
    static idPrefix = "";

    constructor(meshlog, data) {
        this._meshlog = meshlog;
        this.data = {}; // db data
        this.flags = {};
        this.dom = null;
        this.time = 0; // created_at
        this.merge(data);
    }

    merge(data) {
        // App shouldn't change data. It is updated on new advertisements
        this.data = {...this.data, ...data};
        this.time = new Date(data.created_at).getTime();
    }

    // override
    createDom(recreate = false) {}
    updateDom() {}

    static onclick(e) {}
    static onmouseover(e) {}
    static onmouseout(e) {}
    static oncontextmenu(e) {}
}

class MeshLogReporter extends MeshLogObject {
    constructor(meshlog, data) {
        super(meshlog, data);
        this.contact_id = -1;
        this.getContactId();
    }

    getStyle() {
        return JSON.parse(this.data.style);
    }

    getContactId() {
        if (this.contact_id == -1) {
            let contact = Object.values(this._meshlog.contacts)
                .find(obj => obj.data.public_key == this.data.public_key);
            if (contact) {
                this.contact_id = contact.data.id;
            }
        }

        return this.contact_id;
    }
}

class MeshLogChannel extends MeshLogObject {
    constructor(meshlog, data) {
        super(meshlog, data);
    }

    isEnabled() {
        return Settings.getBool(`channels.${this.data.id}.enabled`, true);
    }

    createDom(recreate = false) {
        if (this.dom && !recreate) return this.dom;

        if (this.dom && this.dom.container && this.dom.container.parentNode) {
            this.dom.container.parentNode.removeChild(this.dom.container);
            this.dom = null;
        }

        const self = this;

        let cb = this._meshlog.__createCb(
            this.data.name,
            '',
            `channels.${this.data.id}.enabled`,
            this.isEnabled(),
            (e) => {
                this.enabled = e.target.checked;
                self._meshlog.__onTypesChanged();
            }
        );

        this.dom = {
            cb: cb,
        };

        return this.dom;
    }
}

class MeshLogContact extends MeshLogObject {
    constructor(meshlog, data) {
        super(meshlog, data);
        this.adv = null;
        this.last = null;
        this.telemetry = null;
        this.marker = null;

        this.flags.dupe = false;
        this.hash = data.public_key.substr(0, 2).toLowerCase();
        this.neighbors_visible = false;

        if (data.advertisement) {
            this.adv = new MeshLogAdvertisement(meshlog, data.advertisement);
            this.last = this.adv;
            delete data.advertisement;
        }

        if (data.telemetry) {
            this.telemetry = data.telemetry; // todo: use object
            delete data.telemetry;
        }
    }

    static onclick(e) {
        this.expanded = !this.expanded;
        this.updateDom();
    }

    static onmouseover(e) {
        // Show marker
        const descid = `c_${this.data.id}`;
        this._meshlog.layer_descs[descid] = {
            paths: [],
            markers: new Set().add(this.data.id),
            warnings: []
        }
        this._meshlog.updatePaths();
    }

    static onmouseout(e) {
        const descid = `c_${this.data.id}`;
        delete this._meshlog.layer_descs[descid];
        this._meshlog.updatePaths();
    }

    static oncontextmenu(e) {
        e.preventDefault();

        this._meshlog.dom_contextmenu
        const menu = this._meshlog.dom_contextmenu;

        while (menu.hasChildNodes()) {
            menu.removeChild(menu.lastChild);
        }

        let saveGpx = (data, name) => {
            let gpxContent = `<?xml version="1.0" encoding="UTF-8"?>\n`;
            gpxContent += `<gpx version="1.1" creator="Meshlog">\n`;
            gpxContent += data;
            gpxContent += `</gpx>`;

            const blob = new Blob([gpxContent], { type: "application/gpx+xml" });
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = name;
            a.click();
        };

        const c = this;

        let miGpx = document.createElement("div");
        miGpx.classList.add('menu-item');
        miGpx.innerText = "Export to GPX";
        miGpx.onclick = (e) => {
            if (c.adv) {
                const wpt = `<wpt lat="${c.adv.data.lat}" lon="${c.adv.data.lon}"><name>${escapeXml(c.data.name)}</name></wpt>\n`;
                saveGpx(wpt, `meshlog_contact_${c.data.id}.gpx`);
            }
        };

        let miAll = document.createElement("div");
        miAll.classList.add('menu-item');
        miAll.innerText = "Export all to GPX";
        miAll.onclick = (e) => {
            let wpt = '';
            Object.entries(c._meshlog.contacts).forEach(([k,v]) => {
                if (v.adv && (v.adv.lat != 0 || v.adv.lon != 0)) {
                    wpt += `<wpt lat="${v.adv.data.lat}" lon="${v.adv.data.lon}"><name>${escapeXml(v.data.name)}</name></wpt>\n`;
                }
            });
            saveGpx(wpt, `meshlog_contacts.gpx`);
        };

        menu.appendChild(miGpx);
        menu.appendChild(miAll);

        menu.style.display = 'block';
        menu.style.left = `${e.pageX}px`;
        menu.style.top = `${e.pageY}px`;
    }

    getLayerDescPrefix() {
        return `nb${this.data.id}:`;
    }

    showNeighbors(rx=true, tx=true, markers=false) {
        if (this.isClient()) return; // not supported

        let contactPairs = {
            pairs: {},
            addPair: (src, dst) => {
                if (!src || !dst) return;
                if (!src.adv || !dst.adv) return;
                if (src.adv.data.lat == 0 && src.adv.data.lon == 0) return;

                const key = `${src.data.id}_${dst.data.id}`;

                if (!contactPairs.pairs.hasOwnProperty(key)) {
                    contactPairs.pairs[key] = {
                        count: 1,
                        src,
                        dst,
                    };
                } else {
                    contactPairs.pairs[key].count += 1;
                }
            }
        };

        Object.entries(this._meshlog.messages).forEach(([k,m]) => {
            let src_id = m.data.contact_id;
            let src = this._meshlog.contacts[src_id] ?? false;

            if (!src) return;

            m.reports.forEach(r => {
                let path = r.data.path ?? undefined;
                if (path == undefined) return; 
                let hashes = path ? path.split(",") : [];

                let reporter = this._meshlog.reporters[r.data.reporter_id] ?? false;
                if (!reporter) return;
                

                // If message is from this contact, neighbor is first path hash
                if (src === this) {
                    if (hashes.length == 0) {
                        // direct to reporter
                        if (reporter.getContactId() == -1) return;

                        contactPairs.addPair(src, this._meshlog.contacts[reporter.getContactId()]);
                    } else {
                        // to neareset contact
                        let nearest = this._meshlog.findNearestContact(this.data.lat, this.data.lon, hashes[0], true);
                        if (nearest.result) {
                            contactPairs.addPair(src, nearest.result);
                        }
                    }
                } else {
                    // decode hashes
                    if (hashes.length == 0) return;
                    let idx = hashes.indexOf(this.hash);
                    if (idx < 0) return;
                    idx += 1;

                    // Link contains contact hash
                    // Simulate link up to contact and check if might be possible

                    let prev = {
                        lat: reporter.data.lat,
                        lon: reporter.data.lon,
                        contact_id: reporter.getContactId()
                    };

                    let end = hashes.length + 1;
                    let contacts = {0: src, [end]: this._meshlog.contacts[reporter.getContactId()] ?? null}

                    for (let i=hashes.length-1;i>=0;i--) {
                        let hash = hashes[i];
                        let nearest = this._meshlog.findNearestContact(prev.lat, prev.lon, hash, true);

                        // Valid repeater found?
                        if (nearest) {
                            if (nearest.matches > 1) {
                                // desc.warnings.push(`Multiple paths (${nearest.matches}) detected to ${hash}. Showing shortest.`);
                            }

                            let current = {
                                lat: nearest.result.adv.data.lat,
                                lon: nearest.result.adv.data.lon,
                                contact_id: nearest.result.data.id
                            };

                            contacts[i+1] = nearest.result;
                            prev = current;
                        } else {
                            contacts[i+1] = null;
                            console.log(`no nearest for hash ${hash}`);
                        }
                    }

                    let cThis = contacts[idx];
                    let cPrev = contacts[idx-1];
                    let cNext = contacts[idx+1];

                    if (!cThis || cThis != this) return;
                    if (cNext) { contactPairs.addPair(cThis, cNext); }
                    if (cPrev) { contactPairs.addPair(cPrev, cThis); }
                }
            });
        });

        Object.entries(contactPairs.pairs).forEach(([k,p]) => {
            let isTx = p.src == this;
            if (isTx && !tx) return;
            if (!isTx && !rx) return;

            let key = `${this.getLayerDescPrefix()}${k}`;
            this._meshlog.layer_descs[key] = {
                paths: [
                    new MeshLogLinkLayer(
                        {
                            lat: p.src.adv.data.lat,
                            lon: p.src.adv.data.lon,
                            contact_id: p.src.data.id
                        },
                        {
                            lat: p.dst.adv.data.lat,
                            lon: p.dst.adv.data.lon,
                            contact_id: p.dst.data.id
                        },
                        {
                            data: {
                                id: 0
                            },
                            getStyle: () => {
                                return {
                                    color: isTx ? 'red' : 'blue',
                                    strokeColor: 'white',
                                    strokeWeight: '1px'
                                };
                            }
                        },
                        false
                    )
                ],
                markers: markers ? new Set([p.src.data.id, p.dst.data.id]) : new Set([this.data.id]),
                warnings: []
            }
        });

        this._meshlog.updatePaths();
        this.neighbors_visible = true;
    }

    hideNeighbors() {
        let prefix = this.getLayerDescPrefix();
        Object.keys(this._meshlog.layer_descs).forEach(k => {
            if (k.startsWith(prefix)) {
                delete this._meshlog.layer_descs[k];
            }
        });
        this._meshlog.updatePaths();
        this.neighbors_visible = false;
    }

    createDom(recreate = false) {
        if (this.dom && !recreate) return this.dom;

        if (this.dom && this.dom.container && this.dom.container.parentNode) {
            this.dom.container.parentNode.removeChild(this.dom.container);
            this.dom = null;
        }

        let divContainer = document.createElement("div");
        let divContact = document.createElement("div");
        let divDetails = document.createElement("div");

        divContact.classList.add("log-entry");
        divContact.instance = this;
        divDetails.hidden = true;

        let imType = document.createElement("img");
        let spDate = document.createElement("span");
        let spHash = document.createElement("span");
        let spName = document.createElement("span");
        let spTelemetry = document.createElement("span");

        imType.classList.add(...['ti']);
        spDate.classList.add(...['sp', 'c']);
        spHash.classList.add(...['sp', 'prio-4']);
        spName.classList.add(...['sp', 't']);
        spTelemetry.classList.add(...['sp', 'sm']);

        divContact.append(spDate);
        divContact.append(imType);
        divContact.append(spHash);
        divContact.append(spName);
        divContact.append(spTelemetry);

        let divDetailsType = document.createElement("div");
        let divDetailsFirst = document.createElement("div");
        let divDetailsKey = document.createElement("div");
        let divDetailsTelemetry = document.createElement("div");
        let btnShowNeighbors = document.createElement("button");

        divDetails.append(divDetailsType);
        divDetails.append(divDetailsFirst);
        divDetails.append(divDetailsKey);
        divDetails.append(divDetailsTelemetry);

        if (!this.isClient()) {
            divDetails.append(btnShowNeighbors);
        }

        divContainer.append(divContact);
        divContainer.append(divDetails);

        const self = this;
        btnShowNeighbors.classList.add('btn');
        btnShowNeighbors.innerText = "Show Neighbors";
        btnShowNeighbors.onclick = (e) => {
            if (self.neighbors_visible) {
                self.hideNeighbors();
            } else {
                self.showNeighbors();
            }

            if (self.neighbors_visible) {
                e.target.innerText = "Hide Neighbors";
                e.target.classList.add("active");
            } else {
                e.target.innerText = "Show Neighbors";
                e.target.classList.remove("active");
            }
        }

        this.dom = {
            container: divContainer,
            contact: divContact,
            details: divDetails,

            contactDate: spDate,
            contactHash: spHash,
            contactName: spName,
            contactIcon: imType,
            contactTelemetry: spTelemetry,

            detailsType: divDetailsType,
            detailsFirst: divDetailsFirst,
            detailsKey: divDetailsKey,
            detailsTelemetry: divDetailsTelemetry,
            btnShowNeighbors: btnShowNeighbors
        };

        divContact.instance = this;
        return this.dom;
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
                receipt = rep.getStyle().color;
            } else {
                iconUrl = 'assets/img/person.svg';
            }
        } else if (this.isRepeater()) {
            iconUrl = 'assets/img/tower.svg';
        } else if (this.isRoom()) {
            iconUrl = 'assets/img/group.svg';
        } else if (this.isSensor()) {
            iconUrl = 'assets/img/sensor.svg';
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
            if (this.isVeryExpired()) {
                icdivch1.classList.add("missing");
            } else if (this.isExpired()) {
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

        this.marker = L.marker([this.adv.data.lat, this.adv.data.lon], { icon: icon }).addTo(map);
        this.updateTooltip();
    }

    updateTooltip(tooltip = undefined) {
        if (this.marker) {
            this.marker.unbindTooltip();

            if (tooltip === undefined) {
                tooltip = `<p class="tooltip-title">${this.adv.data.name} <span class="tooltip-hash">[${this.hash}]</span></p><p class="tooltip-detail">Last heard: ${this.last.data.created_at}</p>`;
            }

            if (tooltip) {
                this.marker.bindTooltip(tooltip);
            }
        }
    }

    __removeEmojis(str) {
            return str.replace(
                /([\u200D\uFE0F]|[\u2600-\u27BF]|\uD83C[\uDC00-\uDFFF]|\uD83D[\uDC00-\uDFFF]|\uD83E[\uDD00-\uDFFF])/g,
                ''
            );
    }

    updateDom() {
        if (!this.dom) return;
        if (!this.adv) return;

        let hashstr = this.data.public_key.substr(0,2);

        this.dom.container.dataset.type = this.adv.data.type;
        this.dom.container.dataset.time = this.last.time;
        this.dom.container.dataset.name = this.__removeEmojis(this.adv.data.name).trim();
        this.dom.container.dataset.hash = hashstr;
        this.dom.container.dataset.first_seen = new Date(this.data.created_at).getTime();

        this.dom.details.hidden = !this.expanded;

        if (this.isVeryExpired()) { // 3 days
            this.dom.contactDate.classList.add("prio-6");
        } else if (this.isExpired()) { // 3 days
            this.dom.contactDate.classList.add("prio-5");
        } else {
            this.dom.contactDate.classList.remove("prio-5");
            this.dom.contactDate.classList.remove("prio-6");
        }

        if (this.flags.dupe) {
            this.dom.contactHash.classList.add("prio-5");
        } if (this.isRepeater()) {
            this.dom.contactHash.classList.add("prio-4");
        } else {
            this.dom.contactHash.classList.remove("prio-4");
            this.dom.contactHash.classList.remove("prio-5");
        }


        let type = '';
        if (this.isClient()) {
            this.dom.contactIcon.src = "assets/img/person.svg";
            type = 'Chat';
        } else if (this.isRepeater()) {
            this.dom.contactIcon.src = "assets/img/tower.svg";
            type = 'Repeater';
        } else if (this.isRoom()) {
            this.dom.contactIcon.src = "assets/img/group.svg";
            type = 'Room';
        }  else if (this.isSensor()) {
            this.dom.contactIcon.src = "assets/img/sensor.svg";
            type = 'Sensor';
        } else {
            this.dom.contactIcon.src = "assets/img/unknown.svg";
        }

        this.dom.detailsType.innerHTML = `<span class="detail-name">Type:</span> <span class="detail-value">${type}</span>`;
        this.dom.detailsFirst.innerHTML = `<span class="detail-name">First Seen:</span> <span class="detail-value">${this.data.created_at}</span>`;
        this.dom.detailsKey.innerHTML = `<span class="detail-name">Public Key:</span> <span class="detail-value">${this.data.public_key}</span>`;

        this.dom.contactName.innerText = this.adv.data.name;
        this.dom.contactDate.innerText = this.last.data.created_at;
        this.dom.contactHash.innerText = `[${hashstr}]`;

        if (this.telemetry) {
            let channels = {};
            for (let i=0;i<this.telemetry.length;i++) {
                const sensor = this.telemetry[i];

                if (!channels.hasOwnProperty(sensor.channel)) {
                    channels[sensor.channel] = {};
                }

                if (!channels[sensor.channel].hasOwnProperty(sensor.name)) {
                    channels[sensor.channel][sensor.name] = [];
                }

                channels[sensor.channel][sensor.name].push(sensor.value);
            }

            // build detail text
            let detail = [];
            let short = '';

            const addMeasurement = (src, dst, key, scale, precision, unit) => {
                if (!src.hasOwnProperty(key)) return;
                if (src[key].size < 1) return;

                let val = (src[key][0] / scale).toFixed(precision);
                let str = `${val}`;
                if (unit) str += ` ${unit}`;
                dst.push(str);
            }

            Object.entries(channels).forEach(([ch,data]) => {
                let meas = [];

                addMeasurement(data, meas, "voltage", 1, 2, "V");
                addMeasurement(data, meas, "current", 1, 3, "mA");
                addMeasurement(data, meas, "temperature", 1, 2,  "°C");
                addMeasurement(data, meas, "humidity", 2, 1, " %");
                addMeasurement(data, meas, "pressure", 1, 1, " hPa");

                if (data.hasOwnProperty("voltage")) {
                    short = `${data["voltage"][0].toFixed(2)} V`;
                }

                detail.push(meas);
            });

            const result = detail
                .filter(arr => arr.length > 0) // remove empty arrays
                .map((arr, i) => `Ch${i + 1}: ${arr.join(', ')}`) // format each remaining array
                .join('<br>'); // join lines

            if (result.length > 0) {
                this.dom.detailsTelemetry.innerHTML = `<span class="detail-name">Telemetry:</span> <span class="detail-value">${result}</span>`;
                this.dom.contactTelemetry.innerHTML = short;

            } else {
                this.dom.detailsTelemetry.innerHTML = '';
                this.dom.contactTelemetry.innerHTML = '';
            }
        }

        if (this.highlight) {
            this.dom.contactName.classList.add("chighlight");
        } else {
            this.dom.contactName.classList.remove("chighlight");
        }

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

    isSensor() {
        return this.adv && this.adv.data.type == 4;
    }

    isReporter() {
        return this._meshlog.isReporter(this.data.public_key);
    }

    pathTag() { return 'c'; }

    checkHash(hash) {
        let chash = this.data.public_key.substr(0, hash.length);
        return chash.toUpperCase() === hash.toUpperCase();
    }

    isExpired() {
        if (!this.last) return true;

        let age = new Date().getTime() - this.last.time;
        return age > (3 * 24 * 60 * 60 * 1000); // 3 days
    }

    isVeryExpired() {
        if (!this.last) return true;

        let age = new Date().getTime() - this.last.time;
        return age > (7 * 24 * 60 * 60 * 1000); // 7 days
    }
}

class MeshLogReport {
    constructor(meshlog, data, contact_id, parent) {
        this._meshlog = meshlog;
        this.data = data;
        this.dom = null;
        this.contact_id = contact_id;
        this.polyline = [];
        this.parent = parent;
    }

    showPath() {
        let sender = this._meshlog.contacts[this.contact_id] ?? false;
        let receiver = this._meshlog.reporters[this.data.reporter_id];
        this._meshlog.showPath(this.data.id, this.data.path, sender, receiver);
    }

    hidePath() {
        this._meshlog.hidePath(this.data.id);
    }

    createDom(recreate = false) {
        if (this.dom && !recreate) return this.dom;

        if (this.dom && this.dom.container && this.dom.container.parentNode) {
            this.dom.container.parentNode.removeChild(this.dom.container);
            this.dom = null;
        }

        let reporter = this._meshlog.reporters[this.data.reporter_id] ?? false;
        if (!reporter) return null;

        let divReport = document.createElement("div");
        let spDate = document.createElement("span");
        let spDot = document.createElement("span");
        let spPath = document.createElement("span");
        let spSnr = document.createElement("span");

        divReport.classList.add('log-entry');
        divReport.instance = this;
        spDate.classList.add(...['sp', 'cc']);
        spDot.classList.add(...['dot']);
        spPath.classList.add(...['sp']);
        spSnr.classList.add(...['sp']);

        let textColor = reporter.getStyle().color;
        let strokeColor = reporter.getStyle().stroke ?? textColor;
        let strokeWeight = reporter.getStyle().weight ?? '1px';
        spDot.innerText = reporter.data.name;
        spDot.style.color = textColor;
        spDot.style.border = `solid ${strokeWeight} ${strokeColor}`;

        spDate.innerText = this.data['created_at'].split(' ').pop();
        spPath.innerText = this.data['path'] || "direct";
        spSnr.innerText = this.data['snr'];

        divReport.append(spDate);
        divReport.append(spDot);
        divReport.append(spPath);
        // divReport.append(spSnr);

        this.dom = {
            container: divReport
        }

        return this.dom;
    }

    static onmouseover(e) {
        this.showPath();
        this._meshlog.updatePaths();
    }

    static onmouseout(e) {
        if (this.parent.dom.input.show.checked) return;
        this.hidePath();
    }

    static oncontextmenu(e) {
        e.preventDefault();

        this._meshlog.dom_contextmenu
        const menu = this._meshlog.dom_contextmenu;

        while (menu.hasChildNodes()) {
            menu.removeChild(menu.lastChild);
        }

        // get paths

        let trk = '';
        let wpt = '';
        Object.entries(this._meshlog.layer_descs).forEach(([k,d]) => {
            for (const p of d.paths) {
                if (!trk) trk += `<trkpt lat="${p.from.lat}" lon="${p.from.lon}"></trkpt>\n`;
                trk += `<trkpt lat="${p.to.lat}" lon="${p.to.lon}"></trkpt>\n`;
            }

            for (const m of d.markers) {
                let c = this._meshlog.contacts[m];
                if (c && c.adv) {
                    wpt += `<wpt lat="${c.adv.data.lat}" lon="${c.adv.data.lon}"><name>${escapeXml(c.data.name)}</name></wpt>\n`;
                }
            }
        });

        trk = `<trk><trkseg>\n${trk}</trkseg></trk>`;

        let miGpx = document.createElement("div");
        miGpx.classList.add('menu-item');
        miGpx.innerText = "Export to GPX";
        miGpx.onclick = (e) => {
            let gpxContent = `<?xml version="1.0" encoding="UTF-8"?>\n`;
            gpxContent += `<gpx version="1.1" creator="Meshlog">\n`;

            gpxContent += wpt;
            gpxContent += trk;
            gpxContent += `</gpx>`;

            const blob = new Blob([gpxContent], { type: "application/gpx+xml" });
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = "location.gpx";
            a.click();
        };

        menu.appendChild(miGpx);

        menu.style.display = 'block';
        menu.style.left = `${e.pageX}px`;
        menu.style.top = `${e.pageY}px`;
    }
}

class MeshLogReportedObject extends MeshLogObject {
    constructor(meshlog, data) {
        let reports = data.reports ?? [];
        delete data.reports;

        super(meshlog, data);
        this.dom = null;
        this.expanded = false;
        this.time = new Date(data.created_at).getTime();
        this.reports = [];

        for (let i=0; i<reports.length; i++) {
            let report = reports[i];
            this.reports.push(new MeshLogReport(meshlog, report, data.contact_id, this));
        }
    }

    // Override!
    getId()   { return `?_${this.data.id}`; }
    getDate() { return {text: "Not Implemented", classList: []}; } // date - 2025-10-10 10:00:00
    getTag()  { return {text: "Not Implemented", classList: []}; } // tag  - [PUBLIC] 
    getName() { return {text: "Not Implemented", classList: []}; } // name - Anrijs
    getText() { return {text: "Not Implemented", classList: []}; } // text - Hello mesh!
    isVisible() { return false; }

    getPathTag() { return "unk"; }

    createDom(recreate = false) {
        if (this.dom && !recreate) return this.dom;

        if (this.dom && this.dom.container && this.dom.container.parentNode) {
            this.dom.container.parentNode.removeChild(this.dom.container);
            this.dom = null;
        }

        // Containers
        let divContainer = document.createElement("div");
        let divLog = document.createElement("div");
        let divReports = document.createElement("div");
        divContainer.dataset.time = this.time;
        divContainer.dataset.type = this.data.type;

        divLog.classList = 'log-entry';
        divLog.instance = this;
        divReports.classList = 'log-entry-reports';
        divReports.hidden = true;

        divContainer.append(divLog);
        divContainer.append(divReports);

        // Lines
        let divLine1 = document.createElement("div");
        let divLine2 = document.createElement("div");
        divLine1.classList.add('log-entry-info');
        divLine2.classList.add('log-entry-msg');
        divLog.append(divLine1);
        divLog.append(divLine2);

        // Text values
        let spDate = document.createElement("span");
        let spTag = document.createElement("span");
        let spPrefix = document.createElement("span");
        let spName = document.createElement("span");
        let spText = document.createElement("span");

        let date = this.getDate();
        let tag = this.getTag();
        let name = this.getName();
        let text = this.getText();

        spDate.classList.add(...['sp', 'c']);
        spDate.classList.add(...date.classList);
        spDate.innerText = date.text;

        spTag.classList.add(...['sp', 'tag']);
        spTag.classList.add(...tag.classList);
        spTag.innerText = tag.text;

        // Check message times
        let sentAt = new Date(this.data.sent_at).getTime();
        let createdAt = new Date(this.data.created_at).getTime();
        if (Math.abs(sentAt - createdAt) > 1000 * 60 * 15) {
            spPrefix.textContent = "⚠️";
            spPrefix.classList.add('warn-icon')
            createTooltip(spPrefix, `Clock out of sync. Sender time: ${this.data.sent_at}`);
        }

        spName.classList.add(...['sp', 't']);
        spName.classList.add(...name.classList);
        spName.innerText = name.text;
        spName.style.background = name.background ?? '';

        spText.classList.add(...['sp']);
        spText.classList.add(...text.classList);
        spText.innerHTML = text.text.linkify();

        if (text.text) {
            // message
            divLine1.append(spDate);
            divLine1.append(spPrefix);
            divLine1.append(spTag);
            divLine2.append(spName);
            divLine2.append(spText);
        } else {
            // advert
            divLine1.append(spDate);
            // divLine1.append(spPrefix);
            // divLine1.append(spTag);
            divLine1.append(spName);
        }

        // Right
        let inputShow = document.createElement("input");
        inputShow.type = "checkbox";
        inputShow.classList.add(...['log-entry-cehckbox']);
        divLine1.appendChild(inputShow);

        inputShow.onclick = (e) => {
            e.stopPropagation();
        }

        this.dom = {
            container: divContainer,
            log: divLog,
            reports: divReports,
            input: {
                show: inputShow
            }
        };

        return this.dom;
    }

    updateDom() {
        if (this.highlight) {
            this.dom.log.classList.add("highlight");
        } else {
            this.dom.log.classList.remove("highlight");
        }

        this.dom.container.hidden = !this.isVisible();
        this.dom.reports.hidden = !this.expanded;

        if (this.expanded) {
            for (let i=0; i<this.reports.length; i++) {
                let report = this.reports[i];
                let dom = report.createDom(false);
                if (dom) {
                    this.dom.reports.append(dom.container);
                }
            }
        } else {
            while (this.dom.reports.firstChild) {
                this.dom.reports.removeChild(this.dom.reports.firstChild);
            }
        }
    }

    isAdvertisement() { return this instanceof MeshLogAdvertisement; }
    isChannelMessage() { return this instanceof ChannelMessage; }
    isDirectMessage() { return this instanceof DirectMessage; }

    static onclick(e) {
        this.expanded = !this.expanded;
        this.updateDom();
    }

    static onmouseover(e) {
        // show paths
        for (let i=0;i<this.reports.length;i++) {
            this.reports[i].showPath();
        }
        this._meshlog.updatePaths();
    }

    static onmouseout(e) {
        // hide path
        if (this.dom.input.show.checked) return;
        for (let i=0;i<this.reports.length;i++) {
            this.reports[i].hidePath();
        }
        this._meshlog.updatePaths();
    }
}

class MeshLogAdvertisement extends MeshLogReportedObject {
    static idPrefix = "a";
    getId()   { return `a_${this.data.id}`; }
    getDate() { return {text: this.data.created_at, classList: []}; }
    getTag()  { return {text: "ADVERT", classList: []}; }
    getName() { return {text: this.data.name, classList: []}; }
    getText() { return {text: "", classList: []}; }
    getPathTag() { return "ADV"; }
    isVisible() { return Settings.getBool('messageTypes.advertisements', true); }
}

class MeshLogChannelMessage extends MeshLogReportedObject {
    static idPrefix = "c";
    getTag()  {
        let chid = this.data.channel_id;
        let ch = this._meshlog.channels[chid] ?? false;
        let chname = ch ? ch.data.name : `Channel ${chid}`;
        return {text: `→ ${chname}`, classList: []};
    }

    getId()   { return `c_${this.data.id}`; }
    getDate() { return {text: this.data.created_at, classList: []}; }
    getName() { return {text: `${this.data.name}`, classList: ['t-bright'], background: str2color(this.data.name)}; }
    getText() { return {text: this.data.message, classList: ['t-white']}; }
    getPathTag() { return "MSG"; }
    isVisible() {
        let chid = this.data.channel_id;
        let ch = this._meshlog.channels[chid] ?? false;
        if (ch) ch = ch.isEnabled();
        return Settings.getBool('messageTypes.channel', true) && ch;
    }
}

class MeshLogDirectMessage extends MeshLogReportedObject {
    static idPrefix = "d";
    getTag()  {
        let text = '→ unknown';
        if (this.reports.length > 0) {
            let repid = this.reports[0].data.reporter_id;
            let reporter = this._meshlog.reporters[repid] ?? false;
            if (reporter) {
                text = `→ ${reporter.data.name}`;
            }
        }
        return {text: text, classList: []};
    }

    getId()   { return `d_${this.data.id}`; }
    getDate() { return {text: this.data.created_at, classList: []}; }
    getName() { return {text: `${this.data.name}`, classList: ['t-bright'], background: str2color(this.data.name) }; }
    getText() { return {text: this.data.message, classList: ['t-white']}; }
    getPathTag() { return "DIR"; }
    isVisible() { return Settings.getBool('messageTypes.direct', false); }
}

class MeshLogLinkLayer {
    constructor(from, to, reporter, circle) {
        this.from = from;
        this.to = to;
        this.reporter = reporter;
        this.circle = circle;
    }
}


class MeshLog {
    constructor(map, logsid, contactsid, stypesid, sreportersid, scontactsid, warningid, errorid, contextmenuid) {
        this.reporters = {};
        this.contacts = {};
        this.channels = {};

        this.messages = {};

        this.map = map;
        this.layer_descs = {};
        this.link_layers = L.layerGroup([]);
        this.visible_markers = new Set();
        this.visible_contacts = {};
        this.links = {};
        this.canvas_renderer = L.canvas({ padding: 0.5 });
        this.dom_logs = document.getElementById(logsid);
        this.dom_contacts = document.getElementById(contactsid);
        this.dom_warning = document.getElementById(warningid);
        this.dom_error = document.getElementById(errorid);
        this.dom_contextmenu = document.getElementById(contextmenuid);
        this.timer = false;
        this.autorefresh = 0;
        this.decor = true;

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
         

        this.dom_settings_types = document.getElementById(stypesid);
        this.dom_settings_reporters = document.getElementById(sreportersid);
        this.dom_settings_contacts = document.getElementById(scontactsid);

        this.dom_contacts.addEventListener('click', this.handleMouseEvent);
        this.dom_contacts.addEventListener('mouseover', this.handleMouseEvent);
        this.dom_contacts.addEventListener('mouseout', this.handleMouseEvent);
        this.dom_contacts.addEventListener("contextmenu", this.handleMouseEvent);

        this.dom_logs.addEventListener('click', this.handleMouseEvent);
        this.dom_logs.addEventListener('mouseover', this.handleMouseEvent);
        this.dom_logs.addEventListener('mouseout', this.handleMouseEvent);
        this.dom_logs.addEventListener("contextmenu", this.handleMouseEvent);

        const menu = this.dom_contextmenu;
        document.addEventListener('click', function () {
            menu.style.display = 'none'; // Hide when clicking anywhere
        });

        this.__init_message_types();
        this.__init_contact_order();
        this.__init_contact_types();
        this.__init_warnings();

        this.link_layers.addTo(this.map);

        this.last = '2025-01-01 00:00:00';
    }

    handleMouseEvent(e) {
        const el = e.target.closest('.log-entry');
        if (!el) return;

        const from = e.relatedTarget;
        const related = (from && el.contains(from));

        const instance = el.instance;
        if (!instance) return;

        const cls = instance.constructor;

        switch (e.type) {
            case 'click':
                if (cls.onclick) cls.onclick.call(instance, e);
                break;
            case 'mouseover':
                if (!related && cls.onmouseover) cls.onmouseover.call(instance, e);
                break;
            case 'mouseout':
                if (!related && cls.onmouseout) cls.onmouseout.call(instance, e);
                break;
            case 'contextmenu':
                if (cls.oncontextmenu) cls.oncontextmenu.call(instance, e);
                break;
        }
    };

    __createCb(label, img, key, def, onchange) {
        let div = document.createElement("div");
        let cb = document.createElement("input");
        let lbl = document.createElement("label");
        let ico = document.createElement("img");

        cb.type = "checkbox";
        cb.checked = Settings.getBool(key, def);
        console.log('create cb: ', key, cb.checked);
        cb.onchange = (e) => {
            Settings.set(key, e.target.checked);
            localStorage[key] = e.target.checked;
            onchange(e);
        };

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

    __createInput(name, key, def, onchange) {
        let div = document.createElement("div");
        let inp = document.createElement("input");

        inp.type = "text";
        inp.value = localStorage[key] ?? def;
        inp.oninput = (e) => {
            localStorage[key] = e.target.value;
            onchange(e);
        };
        inp.placeholder = name;

        div.classList.add("settings-input");
        div.appendChild(inp);

        return div;
    }

    __onTypesChanged() {
        this.updateMessagesDom();
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
            if (type == 1 && !Settings.getBool('contactTypes.clients', true)) { hidden = true; }
            else if (type == 2 && !Settings.getBool('contactTypes.repeaters', true)) { hidden = true; }
            else if (type == 3 && !Settings.getBool('contactTypes.rooms', true)) { hidden = true; }
            else if (type == 4 && !Settings.getBool('contactTypes.sensors', true)) { hidden = true; }

            if (!hidden) {
                let filter = Settings.get('contactFilter.value', '').trim().toLowerCase();
                if (filter) {
                    let cmp1 = item.dataset.name.toLowerCase().includes(filter);
                    let cmp2 = item.dataset.hash.toLowerCase().includes(filter);
                    hidden = !cmp1 && !cmp2;
                }
            }

            item.hidden = hidden;
            this.dom_contacts.appendChild(item)
        });
    }

    __init_contact_order() {
        let orders = [
            {
                name: 'Last Heard',
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
            {
                name: 'First Seen',
                fn: (a, b) => {
                    return b.dataset.first_seen.localeCompare(a.dataset.first_seen);
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
                'contactTypes.repeaters',
                true,
                (e) => {
                    self.sortContacts();
                }
            )
        );
        this.dom_settings_contacts.appendChild(
            this.__createCb(
                "",
                "assets/img/person.svg",
                'contactTypes.clients',
                true,
                (e) => {
                    self.sortContacts();
                }
            )
        );
        this.dom_settings_contacts.appendChild(
            this.__createCb(
                "",
                "assets/img/group.svg",
                'contactTypes.rooms',
                true,
                (e) => {
                    self.sortContacts();
                }
            )
        );
        this.dom_settings_contacts.appendChild(
            this.__createCb(
                "",
                "assets/img/sensor.svg",
                'contactTypes.sensors',
                true,
                (e) => {
                    self.sortContacts();
                }
            )
        );
        this.dom_settings_contacts.appendChild(
            this.__createInput(
                "Filter by Name or Hash/Key",
                'contactFilter.value',
                '',
                (e) => {
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
                'messageTypes.advertisements',
                true,
                (e) => {
                    self.__onTypesChanged();
                }
            )
        );

        this.dom_settings_types.appendChild(
            this.__createCb(
                "Channel Messages",
                "assets/img/message.png",
                'messageTypes.channel',
                true,
                (e) => {
                    self.__onTypesChanged();
                }
            )
        );

        this.dom_settings_types.append(
            this.__createCb(
                "Direct Messages",
                "assets/img/message.png",
                'messageTypes.direct',
                false,
                (e) => {
                    self.__onTypesChanged();
                }
            )
        );

        this.dom_settings_types.append(
            this.__createCb(
                "🐐",
                "",
                'notifications.enabled',
                false,
                (e) => { }
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
        });
    }

    __init_warnings() {
        this.dom_warning_messages = document.createElement("div");
        this.dom_warning_messages.classList.add("warnings");
        this.dom_warning_messages_btn = document.createElement("button");
        this.dom_warning_messages_btn.innerText = "Show less";
        this.dom_warning_messages_btn.classList.add("btn");
        this.dom_warning_messages_btn.onclick = (e) => {
            let str = "Show more";
            let com = "1";
            if (this.dom_warning.dataset.compact === com) {
                com = "0";
                str = "Show less";
            }
            this.dom_warning.dataset.compact = com;
            this.dom_warning_messages_btn.innerText = str;
            this.updatePaths();
        }

        this.dom_warning.append(this.dom_warning_messages);
        this.dom_warning.append(this.dom_warning_messages_btn);
    }

    __addObject(dataset, id, obj) {
        if (dataset.hasOwnProperty(id)) {
            dataset[id].merge(obj.data);
        } else {
            dataset[id] = obj;
        }
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

    showWarning(msg) {
        this.dom_warning_messages.innerText = msg;
        if (msg.length > 0) {
            this.dom_warning.hidden = false;
        } else {
            this.dom_warning.hidden = true;
        }
    }

    showError(message, timeout=0) {
        this.setAutorefresh(0);
        this.dom_error.innerHTML = message;
        this.dom_error.classList.add('show');

        // Auto-hide after duration
        const self = this;
        if (timeout > 0) {
           setTimeout(() => {
                self.dom_error.classList.remove('show');
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
            const obj = new klass(this, o);
            const id = klass.idPrefix + o.id;
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

        Object.entries(this.messages).forEach(([k,v]) => {
            if (v instanceof MeshLogAdvertisement) {
                if (v.time < oldest_adv) oldest_adv = v.time;
            } else if (v instanceof MeshLogChannelMessage) {
                if (v.time < oldest_grp) oldest_adv = v.time;
            } else if (v instanceof MeshLogDirectMessage) {
                if (v.time < oldest_dm) oldest_adv = v.time;
            }
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
            const rep = self.__loadObjects(self.direct_messages, data, MeshLogDirectMessage);
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

            const rep3 = this.__loadObjects(this.messages, data.advertisements, MeshLogAdvertisement);
            const rep5 = this.__loadObjects(this.messages, data.channel_messages, MeshLogChannelMessage);
            const rep6 = this.__loadObjects(this.messages, data.direct_messages, MeshLogDirectMessage);

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
        let repHashes = {};
        Object.entries(this.contacts).forEach(([id,contact]) => {
            if (contact.isRepeater()) {
                let hashstr = contact.data.public_key.substr(0,2);
                if (!repHashes.hasOwnProperty(hashstr)) {
                    repHashes[hashstr] = 1;
                } else {
                    repHashes[hashstr] += 1;
                }
            }
        });

        Object.entries(this.contacts).forEach(([id,contact]) => {
            let latest = contact.last;
            if (!latest) return;

            // Mark dupes
            if (contact.isRepeater()) {
                let hashstr = contact.data.public_key.substr(0,2);
                let count = repHashes[hashstr];
                contact.flags.dupe = count > 1;
            }

            this.addContact(contact);
            contact.addToMap(this.map);
        });
        this.updateContactsDom();
    }

    onLoadChannels() {
        Object.entries(this.channels).forEach(([id,channel]) => {
            this.addChannel(channel);
        });
    }

    addChannel(ch) {
        let isnew = ch.dom ? false : true;
        let dom = ch.createDom();
        ch.updateDom();
        if (isnew) {
            this.dom_settings_reporters.appendChild(dom.cb);
        }
    }

    addMessage(msg) {
        let isnew = msg.dom ? false : true;
        let dom = msg.createDom();
        msg.updateDom();

        if (isnew) {
            // find pos by date
            let inserted = false;
            let newTime = dom.container.dataset.time;
            for (let child of this.dom_logs.children) {
                const childTime = child.dataset.time;
                if (newTime > childTime) {
                    this.dom_logs.insertBefore(dom.container, child);
                    inserted = true;
                    break;
                }
            }

            // If not inserted, append at the end
            if (!inserted) this.dom_logs.appendChild(dom.container);

            if (this.contacts.hasOwnProperty(msg.data.contact_id)) {
                let contact = this.contacts[msg.data.contact_id];
                if (!contact.last || msg.time > contact.last.time) {
                    contact.last = msg;
                    if (msg instanceof MeshLogAdvertisement) {
                        contact.adv = msg;
                    }
                }
            }
            this.onNewMessage(msg);
        }
    }

    addContact(msg) {
        let dom = msg.createDom();
        msg.updateDom();
        this.dom_contacts.appendChild(dom.container);
    }

    updateContactsDom() {
        this.sortContacts();
    }

    updateMessagesDom() {
        for (const [key, msg] of Object.entries(this.messages)) {
            msg.createDom(false);
            msg.updateDom();
        }
    }

    onLoadMessages() {
        Object.entries(this.messages).forEach(([id, msg]) => { this.addMessage(msg); });
        this.updateMessagesDom();
    }

    onLoadAll() {
        this.onLoadMessages();
        this.onLoadContacts();
        this.onLoadChannels();
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
            const sz = this.__loadObjects(this.direct_messages, data, MeshLogDirectMessage);
            console.log(`${sz} direct messages loaded`);
            if (onload) onload();
        });
    }

    fadeMarkers(opacity=0.2) {
        const empty = this.visible_markers.size < 1;
        Object.entries(this.contacts).forEach(([k,v]) => {
            if (!v.marker) return;
            if (empty || this.visible_markers.has(v.data.id)) {
                v.marker.setOpacity(1);
                v.marker.setZIndexOffset(1000);
                v.updateTooltip();
            } else {
                v.marker.setOpacity(opacity);
                v.marker.setZIndexOffset(2);
                v.updateTooltip('');
            }
        });
    }

    findNearestContact(lat, lon, hash, repeater) {
        let matches = 0;
        let match = false;
        let matchDist = 99999;

        Object.entries(this.contacts).forEach(([k,c]) => {
            if (c.checkHash(hash) && c.adv && !c.isVeryExpired() && (!repeater || c.isRepeater())) {
                let current = [c.adv.data.lat, c.adv.data.lon];
                if (current[0] == 0 && current[1] == 0) return;

                matches++;
                const dist = haversineDistance(lat, lon, current[0], current[1]);

                if (!match || dist < matchDist) {
                    match = c;
                    matchDist = dist;
                }
            }
        });

        if (!match) return false;

        return {
            result: match,
            distance: matchDist,
            matches: matches
        };
    }

    updatePaths() {
        // remove all layers
        const self = this;
        this.link_layers.eachLayer(function (layer) {
            self.link_layers.removeLayer(layer);
        });

        this.visible_markers.clear();

        // temp, dupe prevention
        let links = [];
        let circles = [];
        let decors = {};
        let warnings = [];

        const ln_weight = 2;
        const ln_outline = 4;
        const ln_decor_weight = 3;
        const ln_decor_outline = 5;
        const ln_offset = 8;
        const ln_repeat = 150;

        const linkColor =  '#555';
        const linkStrokeColor = '#fff';

        Object.entries(this.layer_descs).forEach(([k,desc]) => {
            for (const cid of desc.markers) {
                this.visible_markers.add(cid);
            }

            for (let i=0;i<desc.paths.length;i++) {
                let path = desc.paths[i];
                let line_uid = [path.from.contact_id, path.to.contact_id].join('_');
                let line_id = [path.from.contact_id, path.to.contact_id].sort((a, b) => a - b).join('_');
                let decor_id = `${path.reporter.data.id}`;
                let circle_id = `${path.to.contact_id}`;

                let linePath = [
                    [path.to.lat, path.to.lon],
                    [path.from.lat, path.from.lon]
                ];

                let line1 = L.polyline(linePath, {renderer: this.canvas_renderer, color: linkStrokeColor, weight: ln_outline});
                let line2 = L.polyline(linePath, {renderer: this.canvas_renderer, color: linkColor, weight: ln_weight});

                if (!links.includes(line_id)) {
                    links.push(line_id);
                    line1.addTo(this.link_layers);
                    line2.addTo(this.link_layers);
                }

                // Circle
                if (path.circle && !circles.includes(circle_id)) {
                    circles.push(circle_id);

                    let r = 1000;
                    let op = .2;

                    let circle = L.circle(linePath[0], {
                        color: linkColor,
                        fillColor: linkColor,
                        fillOpacity: op,
                        radius: r
                    });
                    circle.addTo(this.link_layers);
                }

                // Decoratinos
                if (!decors.hasOwnProperty(line_uid)) {
                    decors[line_uid] = [];
                }

                if (!decors[line_uid].includes(decor_id)) {
                    const offset = ln_offset * decors[line_uid].length; // TODO - should increase per 
                    decors[line_uid].push(decor_id);

                    const strokeColor = path.reporter.getStyle().stroke ?? linkStrokeColor;

                    const decorator1 = L.polylineDecorator(line1, {
                        patterns: [
                        {
                            offset: offset,
                            repeat: ln_repeat,
                            symbol: L.Symbol.arrowHead({
                                pixelSize: 10,
                                polygon: false,
                                pathOptions: { renderer: this.canvas_renderer, stroke: true, color: strokeColor, weight: ln_decor_outline }
                            })
                        },
                        {
                            offset: offset,
                            repeat: ln_repeat,
                            symbol: L.Symbol.arrowHead({
                                pixelSize: 10,
                                polygon: false,
                                pathOptions: { renderer: this.canvas_renderer, stroke: true, color: path.reporter.getStyle().color, weight: ln_decor_weight }
                            })
                        }
                    ]
                    });

                    if (this.decor) {
                        decorator1.addTo(this.link_layers);
                    }
                }

                // Markers
                this.visible_markers;
            }
            warnings = [...warnings, ...desc.warnings];
        });
        warnings = [...new Set(warnings)];
        let warningsStr = `${warnings.length} Path warnings`;
        if (this.dom_warning.dataset.compact != "1") {
            warningsStr = warnings.join("\n");
        }
        this.showWarning(warningsStr);
        this.fadeMarkers();
    }

    // Only adds descriptors, not layers
    showPath(id, path, src, reporter) {
        if (this.layer_descs.hasOwnProperty(id)) return;

        let hashes = path ? path.split(',') : [];
        let prev = {
            lat: reporter.data.lat,
            lon: reporter.data.lon,
            contact_id: reporter.getContactId()
        };

        let addCircle = false;

        if (src && !src.isClient()) {
            hashes.unshift(src.data.public_key);
        } else {
            addCircle = true;
        }

        let desc = {
            paths: [],
            markers: new Set(),
            warnings: []
        }

        for (let i=hashes.length-1;i>=0;i--) {
            let hash = hashes[i];
            let nearest = this.findNearestContact(prev.lat, prev.lon, hash, true);

            // Valid repeater found?
            if (nearest) {
                if (nearest.matches > 1) {
                    desc.warnings.push(`Multiple paths (${nearest.matches}) detected to ${hash}. Showing shortest.`);
                }

                let current = {
                    lat: nearest.result.adv.data.lat,
                    lon: nearest.result.adv.data.lon,
                    contact_id: nearest.result.data.id
                };
                desc.markers.add(nearest.result.data.id);
                desc.paths.push(new MeshLogLinkLayer(prev, current, reporter, addCircle && i == 0));
                prev = current;
            } else {
                console.log('no nearest: ');
            }
        }

        this.layer_descs[id] = desc;
    }

    hidePath(id) {
        if (!this.layer_descs.hasOwnProperty(id)) return;
        delete this.layer_descs[id];
    }

    refresh() {
        clearTimeout(this.timer);
        const self = this;
        this.loadNew((data) => {
            const count = Object.keys(this.new_messages).length;
            if (count) {
                if (Settings.getBool('notifications.enabled', false)) {
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
        if (msg instanceof MeshLogChannelMessage || msg instanceof MeshLogDirectMessage) {
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

    function escapeXml(str) {
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&apos;");
}

function str2color(str, saturation = 65, lightness = 45) {
    let hash = 0x811c9dc5n;
    for (let i = 0; i < str.length; i++) {
        hash = BigInt.asIntN(32, hash ^ BigInt(str.charCodeAt(i)));
        hash = BigInt.asIntN(32, hash * 0x01000193n);
    }

    return `hsl(${Number(hash & 0xFFFFFFFFn) % 360}deg, ${saturation}%, ${lightness}%)`;
}

function createTooltip(element, contents) {
    let tooltip = document.createElement("div");
    tooltip.textContent = contents;
    tooltip.classList.add('warn-tooltip');

    element.append(tooltip);

    element.addEventListener("mouseenter", (e) => {
        const rect = element.getBoundingClientRect();
        tooltip.style.display = "block";
        tooltip.style.left = (rect.right + window.scrollX + 8) + "px";
        tooltip.style.top = (rect.top + window.scrollY + (rect.height / 2) - (tooltip.offsetHeight / 2)) + "px";
    });

    element.addEventListener("mouseleave", () => {
        tooltip.style.display = "none";
    });
}
