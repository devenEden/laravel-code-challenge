<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param  User  $user
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  int  $terms
     * @param  string  $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        //
        $loan =  Loan::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'currency_code' => $currencyCode,
            'terms' => $terms,
            'processed_at' => $processedAt,
            'status' => Loan::STATUS_DUE
        ]);

        $scheduledPayments = [];
        $periodAmount = intdiv($amount, $terms);
        $amountRemainder = $amount % $terms; // get the remainder from the division

        for ($term = 1; $term <= $terms; $term++) {
            # code...
            $scheduledPayments[] = [
                'loan_id' => $loan->id,
                'amount' => $periodAmount,
                'outstanding_amount' => $periodAmount,
                'currency_code' => $currencyCode,
                'due_date' =>  date('Y-m-d', strtotime($processedAt . "+ " . $term . ' month')),
                'status' => Loan::STATUS_DUE
            ];
        }

        // spread the remainder across scheduled payments such that we end up with the full amount
        for ($i = 0; $i < $amountRemainder; $i++) {
            # code...
            $scheduledPayments[$i]['amount']++;
            $scheduledPayments[$i]['outstanding_amount']++;
        }


        ScheduledRepayment::insert($scheduledPayments);

        return $loan;
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        $reducingAmount  = $amount;
        while ($reducingAmount > 0) {
            # code...
            $scheduledPayment = $loan->scheduledRepayments()
                ->where(
                    'outstanding_amount',
                    '>',
                    0
                )
                ->orderBy('due_date')->first();

            if ($scheduledPayment->outstanding_amount == $amount) {
                // handle full payment of a scheduled repayment (if payment same as scheduled payment then set )
                $scheduledPayment->outstanding_amount = 0;
                $scheduledPayment->status = ScheduledRepayment::STATUS_REPAID;
                $reducingAmount = 0;
            } else if ($scheduledPayment->outstanding_amount > $reducingAmount) {
                // handle partial payment
                $scheduledPayment->outstanding_amount = $scheduledPayment->outstanding_amount - $reducingAmount;
                $reducingAmount = 0;
                $scheduledPayment->status = ScheduledRepayment::STATUS_PARTIAL;
            } else if ($scheduledPayment->outstanding_amount < $reducingAmount) {
                // handle over payment of a scheduled payment
                $reducingAmount = $reducingAmount - $scheduledPayment->outstanding_amount;
                $scheduledPayment->outstanding_amount = 0;
                $scheduledPayment->status = ScheduledRepayment::STATUS_REPAID;
            }
            $scheduledPayment->save();
            $repayment = ReceivedRepayment::create([
                'loan_id' => $loan->id,
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'received_at' => $scheduledPayment->due_date
            ]);

            // handle case where the amount is over the loan amount
            if (empty($scheduledPayment) && $reducingAmount > 0) {
                break;
            }
        }

        $totalPaid = $loan->scheduledRepayments()->sum('outstanding_amount');

        $loan->outstanding_amount = $totalPaid;
        $loan->status = $totalPaid == 0 ? Loan::STATUS_REPAID : Loan::STATUS_DUE;
        $loan->save();



        return $repayment;
    }
}
