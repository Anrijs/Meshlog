<?php
    require_once __DIR__ . '/../lib/meshlog.class.php';
    require_once __DIR__ . '/../config.php';

    session_start();

    $meshlog = new MeshLog($config['db']);
    $user = null;

    if (isset($_SESSION['user'])) {
        $user = $_SESSION['user'];
    }

    if (isset($_POST['logout']) || isset($_GET['logout'])) {
        session_destroy();
        $user = null;
        header("Location: .");
        exit;
    }

    if (!$user && isset($_POST['login'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $login = MeshLogUser::login($meshlog, $username, $password);
        if ($login) {
            $user = array(
                'id' => $login->getId(),
                'name' => $login->name,
                'permissions' => $login->permissions
            );
            $_SESSION['user'] = $login;

            if (isset($_GET['setup']) || isset($_POST['setup'])) {
                header("Location: ../setup.php");
                exit;
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meshlog Login</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .rcolor {
            display: inline-block;
            height: 1rem;
            width: 1rem;
            border: solid 1px #222;
            border-radius: 1rem;
        }
        td, th {
            padding: 2px 4px;
        }
        tr.disabled {
            text-decoration: line-through;
        }
        input[type=color] {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            border: none;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 1.5rem;
            background: none;
            padding: 0;
            cursor: pointer;
            vertical-align: bottom;
            margin-left: 8px;
        }
        input[type="color"]::-webkit-color-swatch-wrapper {
            padding: 0;
        }
        input[type="color"]::-webkit-color-swatch {
            border: none;
            border-radius: 0;
        }
        input[type="color"]::-moz-color-swatch {
            border: none;
        }
        .logger-name {
            margin-left: 8px;
            padding: 2px;
            font-size: 0.8rem;
            border-radius: 0.35rem;
        }
    </style>
</head>
<body>
<?php if (!$user): ?>
    <div id="login">
        <section>
            <h1>Login</h1>
            <form action="" method="post">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password">
                </div>
                <div class="form-group right">
                    <input type="submit" name="login" value="Login">
                </div>
                <input type="hidden" name="setup" value="1">
            </form>
        </section>
    </div>
<?php else: ?>
    <a href="?logout">Log out</a>
    <h1>Reporters</h1>
    <table >
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Public Key</th>
                <th colspan="2">Location</th>
                <th>Auth</th>
                <td></td>
                <td></td>
            </tr>
        </thead>
        <tbody id="reporters"></tbody>
    </table>

    <script>
        const reporters = document.getElementById("reporters");

        function loadReporters() {
            fetch('api/reporters/', {
                method: "GET",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
            })
            .then(response => response.json())
            .then(result => {
                reporters.innerHTML = '';
                result.objects.forEach(obj => { addReporter(reporters, obj) });
                addReporter(reporters, {
                    id: 'Add',
                    name: 'New Logger',
                    public_key: '',
                    auth: '',
                    lat: '0.00000',
                    lon: '0.00000',
                    authorized: 1,
                    style: '{"color": "#ff0000"}',
                    header: 'Add New new Reporter'
                });
            });
        }

        function makeColorInput(cell, value, onchange) {
            picker = document.createElement("input");
            picker.type = 'color';
            picker.value = value;
            picker.oninput = (e) => {
                onchange(e.target.value);
            }
            cell.append(picker);
            return picker;
        }

        function makeInputCell(row, value, type='text') {
            let td = row.insertCell();
            let input = document.createElement("input");
            td.append(input);

            const isColor = type == 'color';
            let picker = false;

            if (isColor) {
                type = 'text';
                picker = document.createElement("input");
                picker.type = 'color';
                picker.value = value;
                picker.oninput = (e) => {
                    input.value = e.target.value;
                    input.style.color = '#1976D2';
                }
                td.append(picker);
            }

            input.oninput = (e) => {
                input.style.color = '#1976D2';
                if (isColor && picker) {
                    picker.value = e.target.value;
                }
            }
            input.type = type;
            if (type == 'checkbox') {
                input.checked = value;
            } else {
                input.value = value;
            }
            return input;
        }

        function addReporter(table, reporter) {
            if (reporter.header ?? false) {
                let h = document.createElement("h2");
                h.innerText = reporter.header;

                let hrow = table.insertRow();
                let hcell = hrow.insertCell();
                hcell.colSpan = 9;
                hcell.append(h);
            }

            const id = reporter['id'];
            let row = table.insertRow();
            row.dataset.id = id;
            let td1 = row.insertCell();
            td1.innerText = id;

            let style = 0;
            try {
                style = JSON.parse(reporter['style']);
            } catch {
                console.log(reporter);
                style = {color: reporter['style'] };
            }

            let name = makeInputCell(row, reporter['name']);
            let key = makeInputCell(row, reporter['public_key']);
            let lat = makeInputCell(row, reporter['lat']);
            let lon = makeInputCell(row, reporter['lon']);
            let auth = makeInputCell(row, reporter['auth']);

            lat.style.maxWidth = '80px';
            lon.style.maxWidth = '80px';

            let tdstyle = row.insertCell();
            let psample = document.createElement("span")
            psample.innerText = reporter['name'];
            psample.classList.add('logger-name');
            psample.style.color = style['color'];
            psample.style.border = `solid 1px ${style['stroke'] ?? style['color']}`;
            let pcolor = makeColorInput(tdstyle, style['color'], value => {
                psample.style.color = value;
            });
            let pstroke = makeColorInput(tdstyle, style['stroke'] ?? style['color'], value => {
                psample.style.border = `solid 1px ${value}`;
            });
            tdstyle.append(psample);

            let authorized = makeInputCell(row, reporter['authorized'], 'checkbox');
            let td2 = row.insertCell();

            let getReporter = () => { 
                console.log(pcolor);
                console.log(pcolor.value);
                return {
                    id: id,
                    name: name.value,
                    public_key: key.value,
                    lat: lat.value,
                    lon: lon.value,
                    auth: auth.value,
                    authorized: authorized.checked ? 1 : 0,
                    style: JSON.stringify({
                        color: pcolor.value,
                        stroke: pstroke.value
                    })
                }
            };

            if (id == 'Add') {
                let btnAdd = document.createElement("button");
                btnAdd.innerText = "Add";
                btnAdd.onclick = () => {
                    saveReporter(getReporter(), true);
                }
                td2.append(btnAdd);
            } else {
                let btnSave = document.createElement("button");
                let btnDelete = document.createElement("button");
                btnSave.innerText = "Save";
                btnDelete.innerText = "Delete";
                td2.append(btnSave);
                td2.append(btnDelete);

                btnSave.onclick = () => {
                    saveReporter(getReporter());
                }

                btnDelete.onclick = () => {
                    deleteReporter(getReporter());
                }
            }
        }

        function getError(result) {
            const status = result.status ?? '?';
            if (status == 'OK') return false;
            return result['error'] ?? 'Unknown error';
        }

        function saveReporter(reporter, add=false) {
            let data = {
                name: reporter.name,
                public_key: reporter.public_key,
                lat: reporter.lat,
                lon: reporter.lon,
                auth: reporter.auth,
                authorized: reporter.authorized,
                style: reporter.style
            };

            if (add) {
                data.add = 1;
            } else {
                data.edit = 1;
                data.id = reporter.id;
            }

            fetch('api/reporters/', {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams(data) // encodes as POST form data
            })
            .then(response => response.json())
            .then(result => {
                const error = getError(result);
                if (error) {
                    alert(error);
                } else {
                    if (add) {
                        location.reload(true);
                    }
                    // clear  color
                    let row = reporters.querySelector(`tr[data-id="${reporter.id}"]`);
                    let inputs = row.querySelectorAll('input');
                    for (const input of inputs) {
                        input.style.color = '';
                    }
                }
            });
        }

        function deleteReporter(reporter) {
            const data = {
                delete: 1,
                id: reporter.id,
            };

            fetch('api/reporters/', {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams(data) // encodes as POST form data
            })
            .then(response => response.json())
            .then(result => {
                const error = getError(result);
                if (error) {
                    alert(error);
                } else {
                    let row = reporters.querySelector(`tr[data-id="${reporter.id}"]`);
                    row.remove();
                }
            });
        }

        loadReporters();
    </script>
<?php endif ?>
</body>
</html>