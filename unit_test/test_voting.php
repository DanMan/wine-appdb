<?php

require_once("path.php");
require_once("test_common.php");
require_once(BASE."include/vote.php");

/* Test to see that voteManager::getVotes() returns the same number of votes as MAX_VOTES */
function test_voteManager_getVotes()
{
    $oVoteManager = new voteManager();

    $iExpected = MAX_VOTES;
    $iReceived = sizeof($oVoteManager->getVotes());

    if($iExpected != $iReceived)
    {
        echo "Expected voteManager::getVotes() to return $iExpected vote objects, got $iReceived.\n";
        return FALSE;
    }

    return TRUE;
}

/* Tests that the votes are saved to the database and that we cannot create more than MAX_VOTES.
   Note that a user always has MAX_VOTES even though they're not in the DB, so we use update instead of create */
function test_vote_update_delete()
{
    $iUserId = 655000;

    for($i = 0; $i < MAX_VOTES+1; $i++)
    {
        $oVote = new vote();
        $oVote->iUserId = $iUserId;
        $oVote->iSlotIndex = $i+1;

        $oVote->update();
    }

    $oVoteManager = new voteManager($iUserId);
    $aVotes = $oVoteManager->getVotes();

    /* First test to see that the legit votes are saved */
    for($i = 0; $i < MAX_VOTES; $i++)
    {
        $iExpected = $i+1;
        $iReceived = $aVotes[$i]->iSlotIndex;
        if($iExpected != $iReceived)
        {
            echo "Expected slot index of $iExpected, got $iReceived instead.\n";
            return FALSE;
        }
    }

    /* There should only be MAX_VOTES number of votes */
    $iExpected = MAX_VOTES;
    $iReceived = sizeof($aVotes);
    if($iExpected != $iReceived)
    {
        echo "Expected $iExpected number of votes, got $iReceived.\n";
        return FALSE;
    }

    /* Now the entries should be gone again */
    $oVoteManager->delete();
    $iExpected = 0;
    $iReceived = 0; /* Incremented below */
    foreach($oVoteManager->getVotes() as $oVote)
    {
        if($oVote->iVoteId)
            $iReceived++;
    }

    if($iExpected != $iReceived)
    {
        echo "Expected $iExpected votes after deletion, got $iReceived\n";
        return FALSE;
    }

    return TRUE;
}

run_test("test_voteManager_getVotes");
run_test("test_vote_update_delete");

