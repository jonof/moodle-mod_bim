<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package mod_bim
 * @copyright 2010 onwards David Jones {@link http://davidtjones.wordpress.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



/****
 * find_student.php
 * - allow coordinator to search for student details
 */

/***
 * bim_allocate_markers( $bim, $cm )
 * - handle the form for allocating markers
 */

require_once($CFG->dirroot.'/lib/grouplib.php' );
require_once($CFG->dirroot.'/mod/bim/coordinator/find_student_form.php' );

function bim_find_student( $bim, $cm ) {
    global $CFG;

    // create the form
    $find_form = new find_student_form( 'view.php', array( 'id' => $cm->id ) );

    // check to see if we just want to show an individual student details
    // this avoids processing the form
    $details = optional_param( 'details', null, PARAM_ALPHANUM );
    if ( strcmp( $details, "yes")==0 ) {
        add_to_log( $cm->course, "bim", "find student",
                "view.php?id=$cm->id&tab=find",
                "single student", $cm->id );

        $userid = optional_param( 'student', 0, PARAM_NUMBER );
        $heading = get_string('bim_find_again_heading', 'bim' );
        print_heading( $heading, "left", 2 );
        print_string( 'bim_find_again_description', 'bim' );
        $find_form->display();
        show_student_details( $bim, $userid, $cm );
    } else if ( ! $find_form->is_submitted() ) {
        add_to_log( $cm->course, "bim", "find student",
                "view.php?id=$cm->id&tab=find",
                "start search", $cm->id );
        print_heading( get_string( 'bim_find_heading', 'bim' ), "left", 2 );
        print_string( 'bim_find_description', 'bim' );

        $find_form->display();
    } else if ( $fromform = $find_form->get_data() ) {
        bim_process_find_student( $fromform, $bim, $cm, $find_form );
    }
}

/*
 * bim_process_find_student( $fromform, $bim )
 * - given a search string, find how many students
 *   are likely to match such a string
 * - if 0, then display message and then the form again
 * - if only 1, then display the details for that student
 * - if more than 1, but less then 200, show table to allow
 *   user to choose
 * - if more then 200, suggest a need to refine the search
 */

function bim_process_find_student( $fromform, $bim, $cm, $find_form ) {
    global $CFG;
    global $DB;

    $search = $fromform->student;

    // see how many students this search will return
    // only want students in this course
    $context = get_context_instance( CONTEXT_COURSE, $cm->course );
    $students = get_users_by_capability( $context, 'mod/bim:student',
            'u.id,u.username,u.firstname,u.lastname,u.email',
            'u.lastname', '', '', '', '', false, true );

    $ids = array_keys( $students );
    $ids_string = implode( ",", $ids );

    $sql = "id in ( $ids_string ) and ";

    // any only those that match search
    // *** Changed this during conversion fo bim2
    //  $concat = " firstname || lastname like '%$search%'";
    //  if ( $CFG->dbfamily == "mysql" ) {
    //    $concat = "concat(firstname,lastname) like '%$search%'";
    //  } else if ( $CFG->dbfamily == "mssql" ) {
    //    $concat = "firstname + lastname like '%$search%'";
    //  }

    $sql .= "( username like '%$search%' or email like '%$search%' or " .
        " firstname like '%$search%' or lastname like '%$search%' ) ";
    $match_count = 0;
    if ( $matches = $DB->get_records_select( "user", $sql, Array(), 'lastname', 'id', 0, 200 ) ) {
        $match_count = count( $matches );
    }

    if ( $match_count == 0 ) {
        add_to_log( $cm->course, "bim", "find student",
                "view.php?id=$cm->id&tab=find",
                "no matching students", $cm->id );
        print_heading( get_string( 'bim_find_none_heading', 'bim' ), 'left', 2 );
        print_string( 'bim_find_none_description', 'bim', $search );
        $find_form->display();
    } else if ( $match_count == 1 ) {
        add_to_log( $cm->course, "bim", "find student",
                "view.php?id=$cm->id&tab=find",
                "1 matching student", $cm->id );
        $userid = array_shift(array_keys($matches));
        print_heading( get_string( 'bim_find_one_heading', 'bim' ), 'left', 2 );
        print_string( 'bim_find_one_description', 'bim', $search );

        $find_form->display();
        $student_details = $DB->get_records_select( "user",
                "id=$userid " );
        show_student_details( $bim, $userid, $cm );
    } else if ( $match_count > 1 && $match_count < 200 ) {
        add_to_log( $cm->course, "bim", "find student",
                "view.php?id=$cm->id&tab=find",
                "$match_count matching students", $cm->id );
        print_heading( get_string( 'bim_find_x_heading', 'bim', $match_count ),
                'left', 2 );
        $a = new StdClass();
        $a->search = $search;
        $a->count = $match_count;
        print_string( 'bim_find_x_description', 'bim', $a );

        $find_form->display();

        print_heading( get_string('bim_find_student_details_heading', 'bim' ), 3 );

        $match_ids = array_keys( $matches );
        // get matching student details
        //    $student_ids_string = implode( ",", $match_ids );
        list( $ssql, $sparams ) = $DB->get_in_or_equal( $match_ids );

        // get the user details of all the students
        $student_details = $DB->get_records_select( "user", "id $ssql", $sparams );
        // details of who has registered
        $feed_details = bim_get_feed_details( $bim->id, $match_ids );

        // Show table with link based on username
        $data = bim_create_details_display( $student_details, $feed_details, $cm );
        $table = bim_setup_details_table( $cm, $bim->id, 0, 'matching' );
        foreach ($data as $row) {
            // add in the details column
            if ( isset( $feed_details[$row["id"]] ) ) {
                $row["details"] = "<a href=\"$CFG->wwwroot/mod/bim/view.php?id=$cm->id".
                    "&tab=find&details=yes&student=" . $row["id"] .
                    '">Show student details</a> ' .
                    "<small>(" . $row['num_entries'] .
                    " posts.)<br />Last post " .  $row['last_post'];
            } else {
                $row["details"] = "No feed registered | " . $row["register"];
            }
            unset( $row["register"] );  unset( $row["id"] ); unset( $row["email"] );
            unset( $row["blog_url"] );  unset( $row["last_post"] );
            unset( $row["num_entries"] );
            $table->data[] = $row;
            // $table->add_data_keyed( $row );
        }
        echo html_writer::table( $table );

        // show student details

    } else {
        add_to_log( $cm->course, "bim", "find student",
                "view.php?id=$cm->id&tab=find",
                "Too many ($match_count) matching students", $cm->id );
        print_heading( get_string( 'bim_find_x_heading', 'bim', $match_count ),
                "left", 2 );
        $a->search = $search;
        $a->count = $match_count;
        print_string( 'bim_find_too_many', 'bim', $a );

        $find_form->display();
    }
}


