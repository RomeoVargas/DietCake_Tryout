<?php
class Thread extends AppModel
{
    const THREAD_TABLE = 'threads';

    public $validation = array(
        'title' => array(
            'length' => array(
                'validate_between', MIN_LENGTH, MAX_LENGTH,
            ),
        )
    );

    /**
     *RETURNS ALL THREADS IN DATABASE
     *@param $limit
     */
    public static function getAll($limit, $search, $filter, $user_id)
    {
        $db = DB::conn();
        $threads = array();
        $query = 'SELECT t.*, u.username FROM ' .self::THREAD_TABLE. ' t INNER JOIN ' .User::USERS_TABLE. ' u 
            WHERE t.user_id = u.user_id ORDER BY created DESC LIMIT ' .$limit;
        if ($search) {
            $query = 'SELECT t.*, u.username FROM ' .self::THREAD_TABLE. ' t INNER JOIN ' .User::USERS_TABLE. ' u 
                WHERE t.user_id = u.user_id AND ' .$search. ' ORDER BY created DESC LIMIT ' .$limit;
        }
        $rows = $db->rows($query);
        if ($filter != 'All Threads'){
            if ($filter == 'My Threads') {
                $query = 'SELECT t.*, u.username FROM ' .self::THREAD_TABLE. ' t INNER JOIN ' .User::USERS_TABLE. ' u 
                    ON t.user_id = u.user_id WHERE u.user_id = ? AND ' .$search. ' ORDER BY created DESC LIMIT ' .$limit;
            } elseif ($filter == 'Threads I commented') {
                $query = 'SELECT t.*, u.username FROM ' .self::THREAD_TABLE. ' t INNER JOIN ' .User::USERS_TABLE. ' u ON t.user_id = u.user_id
                    WHERE t.thread_id = ANY (SELECT DISTINCT t.thread_id FROM ' .self::THREAD_TABLE. ' t INNER JOIN ' .Comment::COMMENT_TABLE. ' c 
                    ON t.thread_id = c.thread_id WHERE c.user_id = ?)  AND ' .$search. ' ORDER BY created DESC LIMIT ' .$limit;
            } elseif ($filter == 'Other people\'s Threads') {
                $query = 'SELECT t.*, u.username FROM ' .self::THREAD_TABLE. ' t INNER JOIN ' .User::USERS_TABLE. ' u 
                    ON t.user_id = u.user_id WHERE u.user_id != ? AND ' .$search. ' ORDER BY created DESC LIMIT ' .$limit;
            }
            $rows = $db->rows($query, array($user_id));
        }
        foreach ($rows as $row) {
            $threads[] = new self($row);
        }
        return $threads;
    }

    /**
     *RETURNS A SPECIFIC THREAD
     *@param $thread_id
     */
    public static function get($thread_id)
    {
        $db = DB::conn();
        $query = 'SELECT * FROM ' .self::THREAD_TABLE. ' WHERE thread_id = ?';
        $where_params = array($thread_id);
        $row = $db->row($query, $where_params);
        return new self($row);
    }

    /**
     *RETURNS TOTAL NUMBER OF THREADS
     */
    public static function getNumRows($search, $filter, $user_id)
    {
        $db = DB::conn();
        $query = 'SELECT COUNT(*) FROM ' .self::THREAD_TABLE;
        if ($search) {            
            $query = 'SELECT COUNT(*) FROM ' .self::THREAD_TABLE. ' WHERE ' .$search;
        }
        $count = $db->value($query);
        if ($filter != 'All Threads'){
            if ($filter == 'My Threads') {
                $query = 'SELECT COUNT(*) FROM ' .self::THREAD_TABLE. ' WHERE ' .$search. ' AND user_id = ?';
            } elseif ($filter == 'Threads I commented') {
                $query = 'SELECT COUNT(*) FROM ' .self::THREAD_TABLE. ' WHERE thread_id = ANY (SELECT DISTINCT 
                    t.thread_id FROM ' .self::THREAD_TABLE. ' t INNER JOIN ' .Comment::COMMENT_TABLE. ' c 
                    ON t.thread_id = c.thread_id WHERE c.user_id = ?) AND ' .$search;
            } elseif ($filter == 'Other people\'s Threads') {
                $query = 'SELECT COUNT(*) FROM ' .self::THREAD_TABLE. ' WHERE ' .$search. ' AND user_id != ?';
            }
            $count = $db->value($query, array($user_id));
        }
        return $count;            
    }

    /**
     *CREATES A NEW THREAD WITH A COMMENT
     *@param $comment
     *@throws ValidationException
     */
    public function create(Comment $comment)
    {
        if (!$this->validate() || !$comment->validate()) {
            throw new ValidationException('invalid thread or comment');
        }
        $db = DB::conn();
        $db->begin();
        $set_params = array('title' => $this->title, 'user_id' => $this->user_id);
        $db->insert(self::THREAD_TABLE, $set_params);
        $this->thread_id = $db->lastInsertId();
        $comment->thread_id = $this->thread_id;
        // write first comment at the same time
        $comment->create();
        $db->commit();
        return $this->thread_id;
    }

    /**
     *EDITS A THREAD AND ADDS A COMMENT FOR IT
     *@param $comment
     *@throws ValidationException
     */
    public function update(Comment $comment)
    {
        if (!$this->validate() || !$comment->validate()) {
            throw new ValidationException('invalid thread or comment');
        }
        $db = DB::conn();
        $db->begin();
        $set_params = array('title' => $this->title);
        $where_params = array('thread_id' => $this->thread_id);
        $db->update(self::THREAD_TABLE, $set_params, $where_params);
        $comment->thread_id = $this->thread_id;
        // write first comment at the same time
        $comment->create();
        $db->commit();
    }
    
    /**
     *DELETES A THREAD AND ALL OF IT'S COMMENTS 
     */
    public function delete(){
        $db = DB::conn();
        $thread_query = 'DELETE FROM ' .self::THREAD_TABLE. ' WHERE thread_id = ?';
        $comment_query = 'DELETE FROM ' .Comment::COMMENT_TABLE. ' WHERE thread_id = ?';
        $where_params = array($this->thread_id);
        $db->query($thread_query, $where_params);    
        $db->query($comment_query, $where_params);    
    }
}
