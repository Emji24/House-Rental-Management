<?php include 'db_connect.php' ?>
<style>
	.on-print{
		display: none;
	}
</style>
<noscript>
	<style>
		.text-center{
			text-align:center;
		}
		.text-right{
			text-align:right;
		}
		table{
			width: 100%;
			border-collapse: collapse
		}
		tr,td,th{
			border:1px solid black;
		}
	</style>
</noscript>

<div class="container-fluid">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-body">
				<div class="col-md-12">
					<hr>
					<div class="row">
						<div class="col-md-12 mb-2">
							<button class="btn btn-sm btn-block btn-success col-md-2 ml-1 float-right" type="button" id="print">
								<i class="fa fa-print"></i> Print
							</button>
						</div>
					</div>

					<div id="report">
						<div class="on-print">
							<p><center>Rental Balances Report</center></p>
							<p><center>As of <b><?php echo date('F, Y') ?></b></center></p>
						</div>

						<div class="row">
							<table class="table table-bordered">
								<thead>
									<tr>
										<th>#</th>
										<th>Tenant</th>
										<th>House #</th>
										<th>Monthly Rate</th>
										<th>Payable Months</th>
										<th>Payable Amount</th>
										<th>Paid</th>
										<th>Outstanding Balance</th>
										<th>Last Payment</th>
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

									if($tenants->num_rows > 0):
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
										<td><?php echo $i++ ?></td>
										<td><?php echo ucwords($row['name']) ?></td>
										<td><?php echo $row['house_no'] ?></td>
										<td class="text-right"><?php echo number_format($monthly_rate, 2) ?></td>
										<td class="text-right"><?php echo $months.' mo/s' ?></td>
										<td class="text-right"><?php echo number_format($payable, 2) ?></td>
										<td class="text-right"><?php echo number_format($paid, 2) ?></td>
										<td class="text-right"><?php echo number_format($outstanding, 2) ?></td>
										<td><?php echo $last_payment ?></td>
									</tr>
									<?php endwhile; ?>
									<?php else: ?>
									<tr>
										<th colspan="9"><center>No Data.</center></th>
									</tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>

				</div>
			</div>
		</div>
	</div>
</div>

<script>
	$('#print').click(function(){
		var _style = $('noscript').clone()
		var _content = $('#report').clone()
		var nw = window.open("","_blank","width=800,height=700");
		nw.document.write(_style.html())
		nw.document.write(_content.html())
		nw.document.close()
		nw.print()
		setTimeout(function(){
			nw.close()
		},500)
	})

	$('#filter-report').submit(function(e){
		e.preventDefault()
		location.href = 'index.php?page=payment_report&'+$(this).serialize()
	})
</script>