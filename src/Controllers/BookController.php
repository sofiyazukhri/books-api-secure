<?php 
namespace App\Controllers; 

use App\Repositories\BookRepository; 
use Psr\Http\Message\ResponseInterface as Response; 
use Psr\Http\Message\ServerRequestInterface as Request; 
use App\Validation\Validator;
  
final class BookController { 
    public function __construct(private BookRepository $books) {} 
  
    // 1. GET /api/books (List, Search, and Limit)
    public function index(Request $r, Response $s): Response { 
        $p   = $r->getQueryParams(); 
       $rows = $this->books->all((string)($p['q'] ?? ''), (int)($p['limit'] ?? 0));
        return $this->json($s, ['count'=>count($rows), 'data'=>$rows]); 
    } 

    // 2. GET /api/books/{id} (Fetch single book)
    public function show(Request $r, Response $s, array $a): Response { 
        $book = $this->books->find((int)$a['id']); 
        return $book ? $this->json($s, $book) 
                     : $this->json($s, ['error'=>'not found'], 404); 
    } 

    // 3. POST /api/books (Create new entry)
public function create(Request $r, Response $s): Response { 
    $body = (array)$r->getParsedBody(); 
    
    // 🚀 FIX: Sanitize the inputs to strip out dangerous script tags!
    if (!empty($body['title']))  $body['title']  = htmlspecialchars($body['title'], ENT_QUOTES, 'UTF-8');
    if (!empty($body['author'])) $body['author'] = htmlspecialchars($body['author'], ENT_QUOTES, 'UTF-8');
    if (!empty($body['genre']))  $body['genre']  = htmlspecialchars($body['genre'], ENT_QUOTES, 'UTF-8');

    $errors = (new Validator())
        ->required('title', 'author', 'year') 
        ->field('title', Validator::nonEmptyString(200), 'title must be 1-200 chars') 
        ->field('author', Validator::nonEmptyString(150), 'author must be 1-150 chars') 
        ->field('year', Validator::intRange(1000, (int)date('Y')), 'year must be 1000..now') 
        ->field('genre', Validator::nonEmptyString(80), 'genre must be ≤ 80 chars') 
        ->validate($body); 

    if ($errors) return $this->json($s, ['errors' => $errors], 400);

    $auth = (array)$r->getAttribute('auth', []);
    $createdBy = (int)($auth['sub'] ?? 0); 

    $id = $this->books->create($body, $createdBy); 
    return $this->json($s, ['message'=>'Book created', 'data'=>$this->books->find($id)], 201) 
                ->withHeader('Location', '/api/books/' . $id); 
}

    // 4. PUT /api/books/{id} (Update dynamic fields)
    public function update(Request $r, Response $s, array $a): Response {
        $id = (int)$a['id'];
        $body = (array)$r->getParsedBody();
        
        $book = $this->books->find($id);
        if (!$book) {
            return $this->json($s, ['error' => 'not found'], 404);
        }

        // 🚀 IDOR DEFENSE: Extract JWT auth tokens to check who is making this request
        $auth = (array)$r->getAttribute('auth', []);
        $userId = (int)($auth['sub'] ?? 0);
        $userRole = $auth['role'] ?? 'member';

        // Enforce ownership: If you're not an admin, you MUST be the original creator
        if ($userRole !== 'admin' && (int)($book['created_by'] ?? 0) !== $userId) {
            return $this->json($s, ['error' => 'Forbidden: You do not own this record'], 403);
        }

        $errors = $this->validate($body, false);
        if ($errors) return $this->json($s, ['errors' => $errors], 400);

        $this->books->update($id, $body);
        return $this->json($s, ['message' => 'Book updated', 'data' => $this->books->find($id)]);
    }
    // 5. DELETE /api/books/{id} (Remove record permanently)
    public function delete(Request $r, Response $s, array $a): Response {
        $auth = (array)$r->getAttribute('auth', []); 
        if (($auth['role'] ?? 'member') !== 'admin') { 
            return $this->json($s, ['error' => 'Admins only'], 403); 
        }

        $id = (int)$a['id'];
        $book = $this->books->find($id);

        if (!$book) {
            return $this->json($s, ['error' => 'not found'], 404);
        }

        $this->books->delete($id);
        return $this->json($s, ['message' => 'Book deleted', 'data' => $book]);
    }
  
    // Private input fields checking mechanism
    private function validate(array $b, bool $requireAll): array { 
        $errors = [];
        if ($requireAll) {
            if (empty($b['title'])) $errors['title'] = 'Title is required';
            if (empty($b['author'])) $errors['author'] = 'Author is required';
            if (empty($b['year'])) $errors['year'] = 'Year is required';
        } else {
            if (array_key_exists('title', $b) && empty($b['title'])) $errors['title'] = 'Title cannot be empty';
            if (array_key_exists('author', $b) && empty($b['author'])) $errors['author'] = 'Author cannot be empty';
            if (array_key_exists('year', $b) && empty($b['year'])) $errors['year'] = 'Year cannot be empty';
        }
        return $errors; 
    } 

   private function json(Response $r, $data, int $status = 200): Response { 
    $r->getBody()->write(json_encode( 
        $data, 
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE 
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT 
    )); 
    return $r->withHeader('Content-Type','application/json; charset=utf-8') 
             ->withStatus($status); 
} 
}