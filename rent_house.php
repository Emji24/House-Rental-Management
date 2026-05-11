<?php 
include('db_connect.php');

if(!isset($_GET['house_id']) || empty($_GET['house_id'])){
    echo '<div class="container-fluid"><div class="alert alert-danger">No house selected.</div></div>';
    exit;
}

$house_id = intval($_GET['house_id']);
$house = $conn->query("
    SELECT h.*, c.name AS cname
    FROM houses h
    INNER JOIN categories c ON c.id = h.category_id
    WHERE h.id = {$house_id}
");

if($house->num_rows == 0){
    echo '<div class="container-fluid"><div class="alert alert-danger">House not found.</div></div>';
    exit;
}

$row = $house->fetch_assoc();
$active_tenant = $conn->query("SELECT id FROM tenants WHERE house_id = {$house_id} AND status = 1 LIMIT 1");
$is_rented = $active_tenant->num_rows > 0;
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 mb-3">
            <a href="index.php?page=houses" class="btn btn-sm btn-secondary">
                <i class="fa fa-arrow-left"></i> Back to Available Houses
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">
                    <b>Selected House</b>
                </div>
                <div class="card-body">
                    <p>House #: <b><?php echo $row['house_no']; ?></b></p>
                    <p>House Type: <b><?php echo $row['cname']; ?></b></p>
                    <p>Description: <b><?php echo $row['description']; ?></b></p>
                    <p>Monthly Rent: <b><?php echo number_format($row['price'], 2); ?></b></p>
                    <p>Status: 
                        <?php if($is_rented): ?>
                            <span class="badge badge-danger">Rented</span>
                        <?php else: ?>
                            <span class="badge badge-success">Available</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    <b>Rent House Form</b>
                </div>
                <div class="card-body">
                    <?php if($is_rented): ?>
                        <div class="alert alert-danger">
                            This house is already rented. Please select another available house.
                        </div>
                    <?php else: ?>
                        <form id="rent-house-form">
                            <input type="hidden" name="house_id" value="<?php echo $row['id']; ?>">

                            <div class="row form-group">
                                <div class="col-md-4">
                                    <label>Last Name</label>
                                    <input type="text" name="lastname" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label>First Name</label>
                                    <input type="text" name="firstname" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label>Middle Name</label>
                                    <input type="text" name="middlename" class="form-control">
                                </div>
                            </div>

                            <div class="row form-group">
                                <div class="col-md-6">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label>Contact Number</label>
                                    <input type="text" name="contact" class="form-control" required>
                                </div>
                            </div>

                            <div class="row form-group">
                                <div class="col-md-6">
                                    <label>Start Date</label>
                                    <input type="date" name="date_in" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label>End Date</label>
                                    <input type="date" name="end_date" class="form-control">
                                </div>
                            </div>

                            <div class="alert alert-info">
                                By clicking Confirm Rent, this house will be marked as rented and the renter will be saved as an active tenant.
                            </div>

                            <button class="btn btn-success" type="submit">
                                <i class="fa fa-check"></i> Confirm Rent
                            </button>
                            <a href="index.php?page=houses" class="btn btn-secondary">Cancel</a>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$('#rent-house-form').submit(function(e){
    e.preventDefault();
    start_load();
    $.ajax({
        url: 'ajax.php?action=rent_house',
        data: new FormData($(this)[0]),
        cache: false,
        contentType: false,
        processData: false,
        method: 'POST',
        type: 'POST',
        success: function(resp){
            if(resp == 1){
                alert_toast('House rented successfully.', 'success');
                setTimeout(function(){
                    location.href = 'index.php?page=houses';
                }, 1200);
            } else if(resp == 2){
                alert_toast('This house is already rented.', 'danger');
                end_load();
            } else {
                alert_toast('Renting failed. Please check the form.', 'danger');
                console.log(resp);
                end_load();
            }
        },
        error: function(err){
            console.log(err);
            alert_toast('An error occurred while renting.', 'danger');
            end_load();
        }
    });
});
</script>
