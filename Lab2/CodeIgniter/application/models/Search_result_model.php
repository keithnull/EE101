<?php

class Search_result_model extends CI_Model{

    public function __construct()
    {
        $this->load->database();
    }

    public function get_hint($authorname=NULL)
    {
        $query=$this->db->query(
            "SELECT authors.*,paper_author_affiliation.num
            FROM authors,
                (SELECT authorid,count(1) AS num FROM paper_author_affiliation GROUP BY authorid)paper_author_affiliation
            WHERE authors.authorid=paper_author_affiliation.authorid AND authors.authorname LIKE '%$authorname%'
            ORDER BY num DESC
            limit 10;"
        );
        return $query->result_array();

    }

    public function get_search_result($authorname="",$begin=0,$end=10)
    {
        $queryForAuthor=$this->db->query(
            "SELECT authors.*,paper_author_affiliation.num
            FROM authors,
                (SELECT authorid,count(1) AS num FROM paper_author_affiliation GROUP BY authorid)paper_author_affiliation
            WHERE authors.authorid=paper_author_affiliation.authorid AND authors.authorname LIKE '%$authorname%'
            ORDER BY num DESC
            LIMIT $begin,$end;"
        );
        if(!$queryForAuthor->result_array())
            return NULL;
        else{
            $result=array();
            foreach($queryForAuthor->result_array() as $row){
                $singleAuthor["authorID"]=$row["AuthorID"];
                $singleAuthor["authorName"]=$row["AuthorName"];
                $singleAuthor["paperNum"]=$row["num"];
                $queryForAffiliation=$this->db->query(
                    "SELECT affiliations.*
                    FROM affiliations,
                        (SELECT affiliationid,count(1)
                        FROM paper_author_affiliation
                        WHERE authorid='".$singleAuthor["authorID"]."'
                        GROUP BY affiliationid
                        ORDER BY count(1) DESC)paper_author_affiliation
                    WHERE affiliations.affiliationid=paper_author_affiliation.affiliationid
                    LIMIT 1;"
                );
                $rowAff=$queryForAffiliation->row_array();
                $singleAuthor["affiliationID"]=$rowAff["AffiliationID"];
                $singleAuthor["affiliationName"]=$rowAff["AffiliationName"];
                array_push($result, $singleAuthor);
            }
            return $result;
        }

    }
}
?>

