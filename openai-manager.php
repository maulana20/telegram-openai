<?php
class cURL
{
    private function _request($method, $action, $data)
    {
        $curl_info = [
            CURLOPT_URL => "{$this->url}/{$action}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => !empty($data) && array_key_exists('file', $data) ? $data : json_encode($data),
            CURLOPT_HTTPHEADER => [
                !empty($data) && array_key_exists('file', $data) ? "Content-Type: multipart/form-data" : "Content-Type: application/json",
                "Authorization: Bearer {$this->key}"
            ]
        ];

        if (empty($data)) {
            unset($curl_info[CURLOPT_POSTFIELDS]);
        }

        $curl = curl_init();

        curl_setopt_array($curl, $curl_info);
        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }
    
    public function get($action, $data = NULL)
    {
        return $this->_request('GET', $action, $data);
    }
    
    public function post($action, $data = NULL)
    {
        return $this->_request('POST', $action, $data);
    }
    
    public function delete($action, $data = NULL)
    {
        return $this->_request('DELETE', $action, $data);
    }
}

class OpenAI extends cURL
{
    protected $url;
    protected $key;
    
    public function __construct()
    {
         $this->url = "https://api.openai.com/v1";
         $this->key = "OPENAI_KEY";
    }
}

function getResponse($result, $success)
{
    if (!empty($result['error'])) {
        $response = ['status' => 'error', 'info' => $result['error']['message']];
    } else {
        $response = ['status' => 'success', 'info' => $success];
    }
    return json_encode($response);
}

if ($_GET['page'] === 'files' && !empty($_POST) && $_POST['action'] === 'upload') {
    if (empty($_POST['purpose']) || empty($_FILES['file'])) {
        $response = ['status' => 'error', 'info' => 'please complete request'];
        echo json_encode($response); exit();
    }
    $tmpFile = $_FILES['file']['tmp_name'];
    $fileName = basename($_FILES['file']['name']);
    $cFile = curl_file_create($tmpFile, $_FILES['file']['type'], $fileName);
    $data = [
        "purpose" => $_POST['purpose'],
        "file" => $cFile,
    ];
    $result = [(new OpenAI()), "POST"]("files", $data);
    echo getResponse($result, "successful uploaded"); exit();
} else if ($_GET['page'] === 'files' && !empty($_POST) && $_POST['action'] === 'delete') {
    $result = [(new OpenAI()), "DELETE"]("files/" . $_POST['id'], []);
    echo getResponse($result, "successful deleted"); exit();
} else if ($_GET['page'] === 'files' && !empty($_POST) && $_POST['action'] === 'download') {
    $result = [(new OpenAI()), "GET"]("files/" . $_POST['id'] . "/content", []);
    echo getResponse($result, "successful download"); exit();
} else if ($_GET['page'] === 'tunes' && !empty($_POST) && $_POST['action'] === 'create') {
    if (empty($_POST['training_file'])) {
        $response = ['status' => 'error', 'info' => 'please complete request'];
        echo json_encode($response); exit();
    }
    $data = [ "training_file" => $_POST['training_file'] ];
    $result = [(new OpenAI()), "POST"]("fine-tunes", $data);
    echo getResponse($result, "successful created"); exit();
} else if ($_GET['page'] === 'tunes' && !empty($_POST) && $_POST['action'] === 'cancel') {
    $result = [(new OpenAI()), "POST"]("fine-tunes/" . $_POST['id'] . "/cancel", []);
    echo getResponse($result, "successful canceled"); exit();
} else if ($_GET['page'] === 'models' && !empty($_POST) && $_POST['action'] === 'delete') {
    $result = [(new OpenAI()), "DELETE"]("models/" . $_POST['id'], []);
    echo getResponse($result, "successful deleted"); exit();
}

function init_page()
{
    switch ($_GET['page']) {
        case "files": echo file_page(); break;
        case "models": echo model_page(); break;
        case "tunes": echo tune_page(); break;
        default: header("location: ?page=files");
    }
}

function empty_page()
{
    echo '<table id="table" class="table table-bordered">
        <thead>
            <tr>
                <th>&nbsp;</th>
            </tr>
        </thead>
    </table>';
}

function file_modal()
{
    echo '<div class="modal fade" id="fileModal" tabindex="-1" role="dialog" aria-labelledby="fileModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Upload File</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="upload-file">
                        <input type="hidden" name="action" value="upload" />
                        <div class="form-group">
                            <label for="purpose">Purpose</label>
                            <input type="text" class="form-control" name="purpose" id="purpose" placeholder="fine-tune">
                        </div>
                        <div class="form-group">
                            <label for="file">File</label>
                            <input type="file" class="form-control" name="file" id="file" placeholder="Enter File">
                        </div>
                     </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="requestModal(\'files\')">Upload</button>
                </div>
            </div>
        </div>
    </div>';
}

function file_page()
{
    $result = [(new OpenAI()), "GET"]("files");
    
    if (empty($result['data'])) {
        echo empty_page();
        return false;
    }
    
    echo '<table id="table" class="table table-bordered">
        <thead>
            <tr>
                <th>no</th>
                <th>id</th>
                <th>purpose</th>
                <th>file name</th>
                <th>bytes</th>
                <th>created</th>
                <th>status</th>
                <th align="center">action</th>
            </tr>
        </thead>
        <tbody>
        ';
        foreach ($result['data'] as $index => $data) {
            $no = $index + 1;
            $data['created_at'] = date('Y-m-d H:i:s', $data['created_at']);
            echo "<tr>
                <td>{$no}</td>
                <td>{$data['id']}</td>
                <td>{$data['purpose']}</td>
                <td>{$data['filename']}</td>
                <td>{$data['bytes']}</td>
                <td>{$data['created_at']}</td>
                <td>{$data['status']}</td>
                <td align='center'>";
            echo '
                    <button class="btn btn-sm btn-outline-danger" onclick="requestAction(\'files\', \'delete\', \''. $data['id'] . '\')">
                        <i class="fa fa-trash-o"></i>
                    </button>
                    &nbsp;
                    <button class="btn btn-sm btn-outline-primary" onclick="requestAction(\'files\', \'download\', \''. $data['id'] . '\')">
                        <i class="fa fa-download"></i>
                    </button>
                </td>
            </tr>';
        }
        echo '
        </tbody>
    </table>';
    
    echo file_modal();
}

function tune_modal()
{
    echo '<div class="modal fade" id="tuneModal" tabindex="-1" role="dialog" aria-labelledby="tuneModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Create Tune</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="create-tune">
                        <input type="hidden" name="action" value="create" />
                        <div class="form-group">
                            <label for="training_file">Training File</label>
                            <input type="text" class="form-control" name="training_file" id="training_file" placeholder="file-XGinujblHPwGLSztz8cPS8XY">
                        </div>
                     </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="requestModal(\'tunes\')">Save</button>
                </div>
            </div>
        </div>
    </div>';
}

function tune_page()
{
    $result = [(new OpenAI()), "GET"]("fine-tunes");
    
    if (empty($result['data'])) {
        echo empty_page();
        return false;
    }
    
    echo '<table id="table" class="table table-bordered">
        <thead>
            <tr>
                <th>no</th>
                <th>id</th>
                <th>model</th>
                <th>fine_tuned_model</th>
                <th>training_files</th>
                <th>created</th>
                <th>updated</th>
                <th>status</th>
                <th align="center">action</th>
            </tr>
        </thead>
        <tbody>
        ';
        foreach ($result['data'] as $index => $data) {
            $no = $index + 1;
            $data['training_files'] = implode(',', array_map(function ($files) {
                return $files['id'];
            }, $data['training_files']));
            $data['created_at'] = date('Y-m-d H:i:s', $data['created_at']);
            $data['updated_at'] = date('Y-m-d H:i:s', $data['updated_at']);
            echo "<tr>
                <td>{$no}</td>
                <td>{$data['id']}</td>
                <td>{$data['model']}</td>
                <td>{$data['fine_tuned_model']}</td>
                <td>{$data['training_files']}</td>
                <td>{$data['created_at']}</td>
                <td>{$data['updated_at']}</td>
                <td>{$data['status']}</td>
                <td align='center'>";
            echo '
                    <button class="btn btn-sm btn-outline-danger" onclick="requestAction(\'tunes\', \'cancel\', \''. $data['id'] . '\')">
                        <i class="fa fa-window-close"></i>
                    </button>
                </td>
            </tr>';
        }
        echo '
        </tbody>
    </table>';
    
    echo tune_modal();
}

function model_page()
{
    $result = [(new OpenAI()), "GET"]("models");
    
    if (empty($result['data'])) {
        echo empty_page();
        return false;
    }
    
    echo '<table id="table" class="table table-bordered">
        <thead>
            <tr>
                <th>no</th>
                <th>id</th>
                <th>created</th>
                <th>owned</th>
                <th align="center">action</th>
            </tr>
        </thead>
        <tbody>
        ';
        foreach ($result['data'] as $index => $data) {
            $no = $index + 1;
            $data['created'] = date('Y-m-d H:i:s', $data['created']);
            echo "<tr>
                <td>{$no}</td>
                <td>{$data['id']}</td>
                <td>{$data['created']}</td>
                <td>{$data['owned_by']}</td>
                <td align='center'>";
            echo '
                    <button class="btn btn-sm btn-outline-danger" onclick="requestAction(\'models\', \'delete\', \''. $data['id'] . '\')">
                        <i class="fa fa-trash-o"></i>
                    </button>
                </td>
            </tr>';
        }
        echo '
        </tbody>
    </table>';
}
?>

<html>
    <head>
        <title>OpenAI Manager</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/dt-1.10.12/datatables.min.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" crossorigin="anonymous" />
        <style>
            .bg-light { background-color: #d7e3ef!important; }
        </style>
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <a class="navbar-brand">OpenAI</a>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <?php foreach (['files', 'tunes', 'models'] as $menu) {
                            $active = !empty($_GET['page']) && $_GET['page'] === $menu ? 'active' : "";
                        ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $active; ?>" href="?page=<?= $menu; ?>"><?= $menu; ?></a>
                        </li>
                        <?php } ?>
                    </ul>
                </div>
                <?php if ($_GET['page'] === 'files') { ?>
                    <button type="button" class="btn btn-sm btn-outline-success" data-toggle="modal" data-target="#fileModal">
                        Upload File
                    </button>
                <?php } else if ($_GET['page'] === 'tunes') { ?>
                    <button type="button" class="btn btn-sm btn-outline-success" data-toggle="modal" data-target="#tuneModal">
                        Create Tune
                    </button>
                <?php } ?>
            </div>
        </nav>
        <div style="padding: 10px;">
            <div id="message"></div>
            <?php init_page(); ?>
        </div>
    </body>
    <script src="https://code.jquery.com/jquery-1.12.4.js" integrity="sha256-Qw82+bXyGq6MydymqBxNPYTaUXXq7c8v3CwiYwLLNXU=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js" integrity="sha384-+sLIOodYLS7CIrQpBjl+C7nPvqq+FbNUBDunl/OZv93DB7Ln/533i8e/mZXLi/P+" crossorigin="anonymous"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/v/dt/dt-1.10.12/datatables.min.js"></script>
    <script>
      $('#table').DataTable();
      
      $.fn.jsonCustom = function() {
        var form = $(this);
        formData = new FormData();
        formParams = form.serializeArray();
        
        $.each(form.find('input[type="file"]'), function(i, tag) {
          $.each($(tag)[0].files, function(i, file) {
            formData.append(tag.name, file);
          });
        });
        
        $.each(formParams, function(i, val) {
          formData.append(val.name, val.value);
        });
        
        return formData;
      };
      
      const alertMessage = document.getElementById('message');
      const alert = (message, type) => {
          var wrapper = document.createElement('div');
          wrapper.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible" role="alert">' + message + '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
          alertMessage.append(wrapper);
      };
      
      const requestModal = (page) => {
        $.ajax({
          type: "POST",
          processData: false,
          contentType: false,
          cache: false,
          enctype: 'multipart/form-data',
          url: "?page=" + page,
          data: $(page == 'files' ? '#upload-file' : '#create-tune').jsonCustom(),
          success: function (result) {
            result = JSON.parse(result);
            $(page == 'files' ? '#fileModal' : '#tuneModal').modal('hide');
            alert(result.info, result.status == "success" ? "success" : "danger");
          }
        });
      };
      
      const requestAction = (page, action, id) => {
        const formData = new FormData();
        formData.append("action", action);
        formData.append("id", id);
        
        $.ajax({
          type: "POST",
          processData: false,
          contentType: false,
          cache: false,
          enctype: 'multipart/form-data',
          url: "?page=" + page,
          data: formData,
          success: function (result) {
            result = JSON.parse(result);
            alert(result.status == "success" ? "successful delete" : result.info, result.status == "success" ? "success" : "danger");
          }
        });
      };
    </script>
</html>
