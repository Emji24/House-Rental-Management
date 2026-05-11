<?php include('db_connect.php');?>

<div class="container-fluid">
	
	<div class="col-lg-12">
		<div class="row mb-4 mt-4">
			<div class="col-md-12">
				
			</div>
		</div>
		<div class="row">
			<!-- Table Panel -->
			<div class="col-md-12">
				<div class="card">
					<div class="card-header">
						<b>List of Payments</b>
						<span class="float:right">
							<a class="btn btn-primary btn-block btn-sm col-sm-2 float-right" href="javascript:void(0)" id="new_payment">
								<i class="fa fa-plus"></i> New Entry
							</a>
						</span>
					</div>
					<div class="card-body">
						<table class="table table-condensed table-bordered table-hover">
							<thead>
								<tr>
									<th class="text-center">#</th>
									<th>Tenant</th>
									<th>House #</th>
									<th>Outstanding Balance</th>
									<th>Last Payment</th>
									<th class="text-center">Action</th>
								</tr>
							</thead>
							<tbody>
								<?php 
								$i = 1;

								$tenants = $conn->query("
									SELECT 
										t.*,
										CONCAT(t.lastname, ', ', t.firstname, ' ', t.middlename) AS name,
										h.house_no,
										h.price 
									FROM tenants t 
									INNER JOIN houses h ON h.id = t.house_id 
									WHERE t.status = 1 
									ORDER BY h.house_no DESC
								");

								while($row = $tenants->fetch_assoc()):

									$date_started = new DateTime($row['date_in']);
									$today = new DateTime(date('Y-m-d'));

									$interval = $date_started->diff($today);
									$months = ($interval->y * 12) + $interval->m;

									// If tenant already started renting, charge at least 1 month
									if ($today >= $date_started && $months < 1) {
										$months = 1;
									}

									// If tenant start date is in the future, no charge yet
									if ($today < $date_started) {
										$months = 0;
									}

									$monthly_rate = floatval($row['price']);
									$payable = $monthly_rate * $months;

									$paid_qry = $conn->query("SELECT SUM(amount) as paid FROM payments WHERE tenant_id = ".$row['id']);
									$last_payment_qry = $conn->query("SELECT * FROM payments WHERE tenant_id = ".$row['id']." ORDER BY unix_timestamp(date_created) DESC LIMIT 1");

									$paid = 0;
									if($paid_qry && $paid_qry->num_rows > 0){
										$paid_row = $paid_qry->fetch_array();
										$paid = $paid_row['paid'] ? floatval($paid_row['paid']) : 0;
									}

									$last_payment = 'N/A';
									if($last_payment_qry && $last_payment_qry->num_rows > 0){
										$last_payment_row = $last_payment_qry->fetch_array();
										$last_payment = date("M d, Y", strtotime($last_payment_row['date_created']));
									}

									$outstanding = $payable - $paid;

									// Do not allow negative outstanding balance
									if ($outstanding < 0) {
										$outstanding = 0;
									}
								?>
								<tr>
									<td class="text-center"><?php echo $i++ ?></td>
									<td>
										<p><b><?php echo ucwords($row['name']) ?></b></p>
									</td>
									<td>
										<p><b><?php echo $row['house_no'] ?></b></p>
									</td>
									<td class="text-right">
										<p><b><?php echo number_format($outstanding, 2) ?></b></p>
									</td>
									<td>
										<p><b><?php echo $last_payment ?></b></p>
									</td>
									<td class="text-center">
										<button class="btn btn-sm btn-outline-primary view_payment" type="button" data-id="<?php echo $row['id'] ?>">
											View
										</button>
									</td>
								</tr>
								<?php endwhile; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<!-- Table Panel -->
		</div>
	</div>	

</div>

<style>
	td{
		vertical-align: middle !important;
	}
	td p{
		margin: unset
	}
	img{
		max-width:100px;
		max-height:150px;
	}
</style>

<script>
	$(document).ready(function(){
		$('table').dataTable()
	})
	
	$('#new_payment').click(function(){
		uni_modal("New payment","manage_payment.php","mid-large")
	})

	$('.edit_payment').click(function(){
		uni_modal("Manage payment Details","manage_payment.php?id="+$(this).attr('data-id'),"mid-large")
	})

	$('.view_payment').click(function(){
		uni_modal("Tenants Payments","view_payment.php?id="+$(this).attr('data-id'),"mid-large")
	})

	$('.delete_payment').click(function(){
		_conf("Are you sure to delete this payment?","delete_payment",[$(this).attr('data-id')])
	})
	
	function delete_payment($id){
		start_load()
		$.ajax({
			url:'ajax.php?action=delete_payment',
			method:'POST',
			data:{id:$id},
			success:function(resp){
				if(resp==1){
					alert_toast("Data successfully deleted",'success')
					setTimeout(function(){
						location.reload()
					},1500)
				}
			}
		})
	}
</script>