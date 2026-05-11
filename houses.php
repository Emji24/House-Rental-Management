<?php include('db_connect.php');?>

<div class="container-fluid">
    <div class="col-lg-12">
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="alert alert-info mb-0">
                    <b>Available Houses</b><br>
                    Select an available house and click <b>Rent House</b>. Properties are managed by the Property Management desktop system.
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <b>House List</b>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:70px">#</th>
                                    <th class="text-center">House</th>
                                    <th class="text-center" style="width:220px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $i = 1;
                                $house = $conn->query("
                                    SELECT 
                                        h.*, 
                                        c.name AS cname,
                                        CASE 
                                            WHEN t.id IS NULL THEN 'Available'
                                            ELSE 'Rented'
                                        END AS rental_status
                                    FROM houses h
                                    INNER JOIN categories c ON c.id = h.category_id
                                    LEFT JOIN tenants t ON t.house_id = h.id AND t.status = 1
                                    ORDER BY h.id ASC
                                ");
                                while($row=$house->fetch_assoc()):
                                    $status = $row['rental_status'];
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $i++ ?></td>
                                    <td>
                                        <p>House #: <b><?php echo $row['house_no'] ?></b></p>
                                        <p><small>House Type: <b><?php echo $row['cname'] ?></b></small></p>
                                        <p><small>Description: <b><?php echo $row['description'] ?></b></small></p>
                                        <p><small>Price: <b><?php echo number_format($row['price'],2) ?></b></small></p>
                                        <p><small>Status: 
                                            <?php if($status == 'Available'): ?>
                                                <span class="badge badge-success">Available</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Rented</span>
                                            <?php endif; ?>
                                        </small></p>
                                    </td>
                                    <td class="text-center">
                                        <?php if($status == 'Available'): ?>
                                            <a href="index.php?page=rent_house&house_id=<?php echo $row['id'] ?>" class="btn btn-sm btn-success">
                                                Rent House
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled>Already Rented</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>  
</div>
<style>
    td{
        vertical-align: middle !important;
    }
    td p {
        margin: unset;
        padding: unset;
        line-height: 1.25em;
    }
</style>
<script>
    $('table').dataTable()
</script>
