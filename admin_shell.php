<?php

// Render the shared administrator sidebar and topbar without duplicating the
// same navigation markup across every protected admin page.
if(!function_exists("easyorder_admin_shell_start"))
{
	function easyorder_admin_shell_start($active_page="")
	{
		global $connect;

		$admin_name = $_SESSION["admin_name"] ?? "Administrator";
		$admin_role = $_SESSION["admin_role"] ?? "Staff";

		// Refresh the displayed identity from the database so renamed accounts and
		// role changes appear consistently throughout the administrator area.
		if($connect && isset($_SESSION["admin_id"]))
		{
			$identity_stmt = mysqli_prepare($connect,"SELECT staff_name,staff_role FROM staff WHERE staff_id=? AND staff_isDelete=0 LIMIT 1");
			if($identity_stmt)
			{
				mysqli_stmt_bind_param($identity_stmt,"s",$_SESSION["admin_id"]);
				if(mysqli_stmt_execute($identity_stmt))
				{
					$identity_result = mysqli_stmt_get_result($identity_stmt);
					if($identity_row = mysqli_fetch_assoc($identity_result))
					{
						$admin_name = $identity_row["staff_name"];
						$admin_role = $identity_row["staff_role"];
						$_SESSION["admin_name"] = $admin_name;
						$_SESSION["admin_role"] = $admin_role;
					}
				}
				mysqli_stmt_close($identity_stmt);
			}
		}

		$navigation = array(
			array("page"=>"admin_dashboard.php","label"=>"Dashboard","icon"=>"DB"),
			array("page"=>"admin_order.php","label"=>"Orders","icon"=>"OR"),
			array("page"=>"admin_product.php","label"=>"Products","icon"=>"PR"),
			array("page"=>"admin_category.php","label"=>"Categories","icon"=>"CA"),
			array("page"=>"admin_member.php","label"=>"Members","icon"=>"ME"),
			array("page"=>"admin_staff.php","label"=>"Staff","icon"=>"ST"),
			array("page"=>"admin_reward.php","label"=>"Rewards","icon"=>"RW"),
			array("page"=>"admin_report.php","label"=>"Reports","icon"=>"RP")
		);

		if($active_page==="")
		{
			$active_page = basename($_SERVER["PHP_SELF"] ?? "");
		}
		?>
		<!-- Shared desktop administrator shell. -->
		<div class="admin-app-shell">
			<aside class="admin-sidebar" aria-label="Administrator navigation">
				<a class="admin-brand" href="admin_dashboard.php" aria-label="EasyOrder administrator dashboard">
					<img src="image/logo.png" width="52" height="52" alt="EasyOrder logo">
					<span><strong>EasyOrder</strong><small>Admin Portal</small></span>
				</a>

				<nav class="admin-navigation">
					<p class="admin-navigation-label">MANAGEMENT</p>
					<?php foreach($navigation as $item): ?>
						<?php $is_active = $active_page===$item["page"]; ?>
						<a href="<?php echo htmlspecialchars($item["page"],ENT_QUOTES,"UTF-8"); ?>" class="<?php echo $is_active ? "active" : ""; ?>"<?php echo $is_active ? ' aria-current="page"' : ""; ?>>
							<span class="admin-nav-icon" aria-hidden="true"><?php echo htmlspecialchars($item["icon"],ENT_QUOTES,"UTF-8"); ?></span>
							<?php echo htmlspecialchars($item["label"],ENT_QUOTES,"UTF-8"); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<div class="admin-sidebar-note">
					<span>System</span>
					<strong>EasyOrder FYP</strong>
				</div>
			</aside>

			<div class="admin-workspace">
				<header class="admin-topbar">
					<div class="admin-topbar-context">
						<span>Administrator workspace</span>
						<strong><?php echo date("l, d F Y"); ?></strong>
					</div>
					<div class="admin-account-summary">
						<span class="admin-avatar" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($admin_name,0,1)),ENT_QUOTES,"UTF-8"); ?></span>
						<span class="admin-account-copy">
							<strong><?php echo htmlspecialchars($admin_name,ENT_QUOTES,"UTF-8"); ?></strong>
							<small><?php echo htmlspecialchars($admin_role,ENT_QUOTES,"UTF-8"); ?></small>
						</span>
						<a class="admin-logout-link" href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
					</div>
				</header>

				<main id="main" class="admin-main">
		<?php
	}
}

// Close the common content area and keep the admin footer inside the workspace.
if(!function_exists("easyorder_admin_shell_end"))
{
	function easyorder_admin_shell_end()
	{
		?>
				</main>
				<footer class="admin-footer">
					<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
				</footer>
			</div>
		</div>
		<?php
	}
}

