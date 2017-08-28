<?php

include_once "/lib/sd_204.php";

echo "PHPoC example : PBH-204 / swing DIO output\r\n";

function dio_out_inc_toggle($loop_count)
{
	for($i = 0; $i < $loop_count; $i++)
	{
		dio_out(DO_0 + $i % 4, TOGGLE);
		usleep(200000);
	}
}

function dio_out_dec_toggle($loop_count)
{
	for($i = 0; $i < $loop_count; $i++)
	{
		dio_out(DO_0 + (3 - $i % 4), TOGGLE);
		usleep(200000);
	}
}

dio_out_inc_toggle(8);
dio_out_dec_toggle(8);

?>
