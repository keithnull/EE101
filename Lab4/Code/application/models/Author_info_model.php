<?php

class Author_info_model extends CI_Model{

    public function __construct()
    {
        $this->load->database();
    }
 
    private function handle_one_paper($row)
    {
        $temp["paperID"]=$row["PaperID"];
        $temp["paperTitle"]=$row["Title"];
        $temp["paperPublishYear"]=$row["PaperPublishYear"];
        $temp["conferenceID"]=$row["ConferenceID"];
        $temp["conferenceName"]=$row["ConferenceName"];
        $temp["citation"]=$row["citation"] ?? 0;
        $temp["authors"]=$this->get_paper_author($row["PaperID"]);
        return $temp;
    }

    public function get_paper_author($paperID=NULL)
    {
        $queryForAuthors=$this->db->query(
            "SELECT authors.AuthorName,paper_author_affiliation.*
            FROM authors, (SELECT * FROM paper_author_affiliation WHERE paperid=\"$paperID\")  paper_author_affiliation
            WHERE paper_author_affiliation.authorid=authors.authorid
            ORDER BY paper_author_affiliation.authorsequence ASC;"
        );
        $authors=array();
        foreach ( $queryForAuthors->result_array() as $subAuthor) {
            $temp["subAuthorName"]=$subAuthor["AuthorName"];
            $temp["subAuthorID"]=$subAuthor["AuthorID"];
            $temp["subAuthorSequence"]=$subAuthor["AuthorSequence"];
            array_push($authors, $temp);
        }
        return $authors;

    }

    public function get_paper_number($authorID=NULL)
    {
        if(!$authorID)
            return NULL;
        else{
            $queryForPaperNum=$this->db->query(
                "SELECT COUNT(*) AS num FROM paper_author_affiliation WHERE authorid='$authorID';");
            $queryForCitedPaperNum=$this->db->query(
                "SELECT COUNT(DISTINCT paper_author_affiliation.paperid) AS num
                FROM paper_author_affiliation,paper_reference 
                WHERE paper_author_affiliation.authorid='$authorID' 
                    AND paper_author_affiliation.paperid=paper_reference.referenceid;"
                );
            $queryForUncitedPaperNum=$this->db->query(
                "SELECT COUNT(*) AS num FROM paper_author_affiliation 
                WHERE paper_author_affiliation.authorid='$authorID' 
                    AND 
                    ( SELECT count(1) FROM paper_reference 
                        WHERE paper_reference.referenceid =paper_author_affiliation.paperid
                    ) = 0;"
                );
            $result=array();
            $result["cited"]=$queryForCitedPaperNum->row_array()["num"];
            $result["uncited"]=$queryForUncitedPaperNum->row_array()["num"];
            $result["all"]=$queryForPaperNum->row_array()["num"];
            return $result;
        }
    }

    public function get_cited_papers($authorID=NULL,$begin=0,$num=10)
    {
        $queryForCitedPaper=$this->db->query(
            "SELECT papers.*,conferences.ConferenceName,paper_reference.citation
            FROM papers,conferences,paper_author_affiliation,
                (SELECT referenceid,count(1) AS citation FROM paper_reference GROUP BY referenceid)paper_reference
            WHERE papers.paperid=paper_reference.referenceid
                AND conferences.conferenceid=papers.conferenceid
                AND papers.paperid=paper_author_affiliation.paperid
                AND paper_author_affiliation.authorid='$authorID'
            ORDER BY citation DESC,papers.title ASC
            LIMIT $begin,$num;"
            );
        return $queryForCitedPaper->result_array();
    }

    public function get_uncited_papers($authorID=NULL,$begin=0,$num=10)
    {
        $queryForUncitedPaper=$this->db->query(
            "SELECT papers.*,conferences.ConferenceName
            FROM papers,conferences,paper_author_affiliation
            WHERE papers.paperid=paper_author_affiliation.paperid 
                AND paper_author_affiliation.authorid=\"$authorID\" 
                AND (Select count(1) FROM paper_reference WHERE paper_reference.referenceid=papers.paperid)=0 
                AND papers.conferenceid=conferences.conferenceid
            ORDER BY papers.title ASC
            LIMIT $begin,$num;"
            );
        return $queryForUncitedPaper->result_array();
    }
    public function get_author_info($authorID=NULL,$begin=0,$num=10,$paperNum=NULL)
    {
        if($authorID==NULL)
            return NULL;
        $paperNum= $paperNum ?? $this->get_paper_number($authorID);
        $result=array();
        $queryForName=$this->db->query("SELECT AuthorName From authors where AuthorID=\"$authorID\"");
        $result["authorName"]=($queryForName->row_array())["AuthorName"];
        $result["authorDescription"]="Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.";
        $result["authorImg"]="/static/img/author.jpg";
        $papers=array();
        if($begin>=0 and $begin+$num<=$paperNum["cited"]){
            foreach ($this->get_cited_papers($authorID,$begin,$num) as $row) {
                array_push($papers, $this->handle_one_paper($row));
            }
        }else if($begin<=$paperNum["cited"]-1 and $begin+$num-1>=$paperNum["cited"]){
            $paperCnt=0;
            foreach ($this->get_cited_papers($authorID,$begin,$num) as $row) {
                $paperCnt+=1;
                array_push($papers, $this->handle_one_paper($row));
            }
            $newNum= $num-$paperCnt;
            foreach ($this->get_uncited_papers($authorID,0,$newNum) as $row) {
                $row["citation"]=0;
                array_push($papers, $this->handle_one_paper($row));
            }            
        }else if($begin>=$paperNum["cited"]){
            $newBegin=$begin-$paperNum["cited"];
            foreach ($this->get_uncited_papers($authorID,$newBegin,$num) as $row) {
                $row["citation"]=0;
                array_push($papers, $this->handle_one_paper($row));
            }
        }
        $result["papers"]=$papers;
        return $result;
    }


    public function get_author_info_old_version($authorID=NULL,$begin=0,$num=10)
    {
        $queryForExistence=$this->db->query("SELECT count(1) AS num FROM paper_author_affiliation WHERE authorid=\"$authorID\";");
        if($queryForExistence->row_array()["num"]==0)
            return NULL;
        $result=array();
        $queryForName=$this->db->query("SELECT AuthorName From authors where AuthorID=\"$authorID\"");
        $result["authorName"]=($queryForName->row_array())["AuthorName"];
        $result["authorDescription"]="<p>This is the description. This is the description.
            This is the description. This is the description. This is the description. This is the description.
            This is the description. This is the description. </p><p>This is the description. This is the description.
            This is the description. This is the description. This is the description. This is the description.
            This is the description. This is the description. </p>";
        $result["authorImg"]="/static/img/author.jpg";
        $queryForPaper=$this->db->query(
            "SELECT papers.*,conferences.ConferenceName,paper_reference.citation
            FROM papers,conferences,paper_author_affiliation,
                (SELECT referenceid,count(1) AS citation FROM paper_reference GROUP BY referenceid)paper_reference
            WHERE papers.paperid=paper_reference.referenceid
                AND conferences.conferenceid=papers.conferenceid
                AND papers.paperid=paper_author_affiliation.paperid
                AND paper_author_affiliation.authorid=\"$authorID\"
            ORDER BY citation DESC
            LIMIT 10;"
        );
        $paperCnt=0;
        $papers=array();
        foreach ($queryForPaper->result_array() as $row) {
            $paperCnt+=1;
            array_push($papers, $this->handle_one_paper($row));
        }
        if($paperCnt<10){
            $extraNum=10-$paperCnt;
            $queryForExtraPaper=$this->db->query(
                "SELECT papers.*,conferences.ConferenceName
                FROM papers,conferences,paper_author_affiliation
                WHERE papers.paperid=paper_author_affiliation.paperid AND paper_author_affiliation.authorid=\"$authorID\" AND
                    (Select count(1) FROM paper_reference WHERE paper_reference.referenceid=papers.paperid)=0 AND
                    papers.conferenceid=conferences.conferenceid
                LIMIT $extraNum;"
            );
            foreach ($queryForExtraPaper->result_array() as $row) {
                $paperCnt+=1;
                $row["citation"]=0;
                array_push($papers, $this->handle_one_paper($row));
            }
        }
        $result["papers"]=$papers;
        return $result;
    }

}

?>