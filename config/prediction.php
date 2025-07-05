<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Hybrid-Edge Prediction Weights
    |--------------------------------------------------------------------------
    |
    | These weights determine the contribution of Elo Probability and Momentum
    | Score to the final prediction score.
    | w_elo + w_mom should ideally sum up to 1.0.
    |
    */
    'w_elo' => env('PRED_W_ELO', 0.65), // Elo 機率的權重 (0-1 之間)
];
