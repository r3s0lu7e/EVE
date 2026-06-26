<?php

namespace App\Services\CostBasis;

interface CostBasisStrategy
{
    /**
     * Match sells against buys and return one result row per pairing.
     *
     * @param  array<int,array{transaction_id:int,date:string,type_id:int,location_id:int|null,quantity:int,unit_price:float,is_buy:bool}>  $transactions
     *         Must be ordered chronologically (date, then transaction_id).
     * @return array<int,array{type_id:int,location_id:int|null,sell_transaction_id:int,buy_transaction_id:int|null,quantity:int,buy_unit_cost:float|null,sell_unit_price:float,sell_date:string,gross_profit:float,unmatched:bool}>
     */
    public function match(array $transactions): array;
}
