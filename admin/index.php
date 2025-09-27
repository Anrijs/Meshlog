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
                result.objects.forEach(obj => addReporter(reporters, obj));
                addReporter(reporters, {
                    id: 'Add', 
                    name: 'New Logger',
                    public_key: '',
                    auth: '',
                    lat: '0.00000',
                    lon: '0.00000',
                    authorized: 1,
                    color: '#ff0000'
                });
            });
        }

        function makeInputCell(row, value, type='text') {
            let td = row.insertCell();
            let input = document.createElement("input");
            input.oninput = () => {
                input.style.color = '#1976D2';
            }
            input.type = type;
            if (type == 'checkbox') {
                input.checked = value;
            } else {
                input.value = value;
            }
            td.append(input);
            return input;
        }

        function addReporter(table, reporter) {
            const id = reporter['id'];
            let row = table.insertRow();
            row.dataset.id = id;
            let td1 = row.insertCell();
            td1.innerText = id;

            let name = makeInputCell(row, reporter['name']);
            let key = makeInputCell(row, reporter['public_key']);
            let lat = makeInputCell(row, reporter['lat']);
            let lon = makeInputCell(row, reporter['lon']);
            let auth = makeInputCell(row, reporter['auth']);
            let color = makeInputCell(row, reporter['color']);
            let authorized = makeInputCell(row, reporter['authorized'], 'checkbox');
            let td2 = row.insertCell();

            let getReporter = () => { 
                return {
                    id: id,
                    name: name.value,
                    public_key: key.value,
                    lat: lat.value,
                    lon: lon.value,
                    auth: auth.value,
                    authorized: authorized.checked ? 1 : 0,
                    color: color.value
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
                color: reporter.color
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