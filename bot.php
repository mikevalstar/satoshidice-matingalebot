<?php
date_default_timezone_set('America/Toronto'); // See: http://www.php.net/manual/en/timezones.php to change timezone
require_once('./lib/jsonRPCClient.php');
require_once('./lib/Logging.php');
require_once('./lib/functions.php');


define('MIN_BET', 0.01);                                // Minimum Bet
define('MAX_BET', 0.10);                                // Maximum Bet
define('TX_FEE', 0.0005);                                // Transaction fee to use
define('ADDRESS', '1dice9wcMu5hLF4g81u8nioL5mmSHTApw'); // Satoshi-Dice Adress to Bet on
define('MULTIPLIER', 2.0);				// multiplier to up bets at
define('MAX_GAMES', 100);                               // Stop after X Won Games
define('RPC_USER', 'mikevalstar');                           //
define('RPC_PASS', 'password');

$b = new jsonRPCClient('http://'.RPC_USER.':'.RPC_PASS.'@127.0.0.1:8332/');

// Validate the bitcoin address
$validate = $b->validateaddress(ADDRESS);
if($validate['isvalid'] != 1) die('Bitcoin address is not valid, stopping bot.');

$bet = MIN_BET;
$total_fees = 0;
$count = 0;
$count_won = 0;

$log = new Logging();
$log->lfile('logs/game');

while (($bet <= MAX_BET) && ($count_won < MAX_GAMES))
{

    $balance_a = $b->getbalance('*', 0);

    if (!isset($starting_balance)){
        $starting_balance = $balance_a;
        $balance_c = $starting_balance;

        $log->lwrite('#############################');
        $log->lwrite('##### Starting new Game #####');
        $log->lwrite('#############################');
        $log->lwrite('Starting Balance : '.$starting_balance);
    }
    if($b->getbalance('*', 1) < $bet) { // If we don't have enough confirmed bitcoins to send to satoshi dice...
        echo "Waiting for confirmed balance";
        $conf = $b->getbalance('*', 1);
        $log->lwrite('Wating for confimation: C: ' . f($conf) . ' | UC: ' . f($balance_a-$conf));
        while($b->getbalance('*', 1) < $bet)
        {
            sleep(1); // Wait a full minute before checking the balance again.
        }
        echo "\n";
    }
	try // Wrapped in a try catch block just incase we run out of cash.
    {
        $b->settxfee(TX_FEE);
        $transaction_id = $b->sendtoaddress(ADDRESS, (float) $bet);
	}
    catch(Exception $e)
    {
        echo "Have: " . f($b->getbalance('*', 1)) . " Needed: " . f($bet) . "\n";
		die("Ran out of money?\n");
	}

    $count++;
    $balance_b = $b->getbalance('*', 0);

    $fee = $balance_a - $balance_b - $bet;
    $total_fees += $fee;
    $total_fees = number_format($total_fees,8,'.','')+0;

    echo 'Bet #'.$count." (W:{$count_won}|L:".(($count-1)-$count_won)."|Q:".(($count > 1)?f(($count_won/($count-1))*100,2):0)."%|TW:".f($balance_c - $starting_balance).") | ".date('D M j H:i:s T  Y', time())."\n";
    echo 'Balance: ' . str_pad(f($balance_a), 15) . 'Bet: '. str_pad($bet, 10) . 'Fee: '. str_pad(f($fee),10) . 'Total Fees: '. str_pad($total_fees, 10). "TransactionID: " . $transaction_id . "\n";
    echo 'Balance: ' . str_pad(f($balance_b), 15) . 'Waiting';

    $balance_c = 0;

    while ($balance_b >= $balance_c)
    {
        sleep(1);
		$balance_c = $b -> getbalance('*', 0);
    }

    echo "\nBalance: " . str_pad(f($balance_c), 15);

    $diff = $balance_c - $balance_b;

    $lookupurl = "http://satoshidice.com/lookup.php?tx=".$transaction_id."&limit=100&min_bet=0&status=ALL&format=json";
    $data = json_decode(@file_get_contents($lookupurl));
    if(isset($data->bets[0])){
        $bets = $data->bets[0];
        echo "Bet: ". str_pad($bets->betname, 24)." Result: ". str_pad($bets->result, 13)." Lucky: ". str_pad($bets->lucky, 10)." Payment: ".str_pad($bets->payment_amt, 10) ." \n";
    }

    if ($diff > $bet)
    {
            $log->lwrite('Bet : '.$bet.' WON: '.$diff);
            $bet = MIN_BET;
            $count_won++;
            echo "Win! ($count_won out of $count)" . "\n";
            if(is_file('stop.txt')){
                echo "\n\n";
                echo "###################" . "\n";
                echo "### Manual Stop ###" . "\n";
                echo "###################" . "\n";
                echo "\n\n";
                break;
            }
    }
    else
    {
            $log->lwrite('Bet : '.$bet.' LOSE');
            $bet *= MULTIPLIER;
            echo "Lose!" . "\n";
    }

    echo "\n";
}

$log->lwrite('Ending Balance      : ' . f($balance_c));
$log->lwrite('Net Profit          : ' . f($balance_c - $starting_balance));
$log->lclose();

showresult();
?>
