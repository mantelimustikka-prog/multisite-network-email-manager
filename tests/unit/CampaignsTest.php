<?php

return array(
    'campaign transitions are explicit' => function () {
        $campaigns = new MNEM_Campaigns();

        mnem_assert_true($campaigns->can_transition('draft', 'scheduled'));
        mnem_assert_false($campaigns->can_transition('draft', 'completed'));
        mnem_assert_true($campaigns->can_transition('sending', 'completed'));
        mnem_assert_false($campaigns->can_transition('completed', 'draft'));
    },
);
