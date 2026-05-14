# Application
___


## User perspective
As a user, I want to be able to store books in a library, so that I can keep track of my collection. The application 
should be able to add books, edit books, remove books, list all books, and download books in the library. Each book 
should have a title, author, publication year, and a place to be stored as a file. The application should also allow 
users to search for books by title or author. The application should be extensible, not only for books but also for 
other types of media such as movies or music. These last two features can be implemented in a future version of the 
application.
___

## Developer perspective
As a developer, I want to create an application that allows users to manage their book collection efficiently. The 
application should be able to handle a large number of books and should be efficient in terms of time and space 
complexity. The application should be implemented in latest stable version of PHP, using the latest stable version of 
Laravel framework with vue as front-end. The code should follow best practices for object-oriented programming, SOLID 
principles, and also following Domain Driven Design Pattern.

The application should be designed with a modular architecture, allowing for easy maintenance and scalability. The 
application should also be designed with security in mind, ensuring that user data is protected and that the application 
is resistant to common web vulnerabilities such as SQL injection and cross-site scripting (XSS). The application should 
also be designed with performance in mind, ensuring that it can handle a large number of books and users without 
significant slowdowns. The application should also be designed with a user-friendly interface, making it easy for users 
to navigate and manage their book collection.

The application should be portable and should be able to run on different platforms and environments, such as local 
development, staging, and production. So, dockerization of the application is a must.

## Business logic
### Book
1. Include book
    - When including a book, the user should provide the title, author, publication year, and a file to be uploaded.
    - The application should validate the input data and ensure that the title and author are not empty, and that the 
publication year is a valid integer.
    - The author field should allow multiple authors to be added for a single book, and the application should handle 
the relationship between books and authors in the database. The associations between books and authors should be done here,
in the book section.
    - A dropdown list of existing authors should be provided, along with an option to add a new author if the desired 
one is not available.
    - The dropdown list should be auto-complete, allowing users to quickly find and select existing authors as they type.
Minimum of 3 characters should be required to trigger the auto-complete functionality.
    - The application should validate the input data and ensure that the file is in a supported format. PDF format is the 
only supported format for now.
2. Edit book
    - When editing a book, the user should be able to update the title, author, publication year, and file.
    - The application should validate the input data as the same way as when including a book.
    - The application should also allow users to remove the association between a book and an author, in case they want 
to update the authorship of a book.
3. Remove book
    - When removing a book, the user should be prompted to confirm the action to prevent accidental deletions.
4. List all books
    - The application should display a list of all books in the library, showing the title, author(s), publication year,
and an option to download the book file.
    - The list should be paginated to improve performance and user experience when dealing with a large number of books.
6. Search for books by title or author
    - The application should provide a search functionality that allows users to search for books by title or author.
Minimum of 3 characters should be required to trigger the search functionality.
    - The search should be case-insensitive and should return results that match the search query in either the title or 
the author fields.
    - The search results should be displayed in a paginated format, similar to the list of all books, to improve 
performance and user experience when dealing with a large number of search results.

### Author
1. Include author
    - When including an author, the user should provide the name of the author.
    - The application should validate the input data and ensure that the name is not empty.
2. Edit author
    - When editing an author, the user should be able to update the name of the author.
    - The application should validate the input data as the same way as when including an author.
3. Remove author
    - When removing an author, the user should be prompted to confirm the action to prevent accidental deletions.
    - If the author is associated with any books, the application should prevent the deletion and display an appropriate 
message to the user, indicating that the author cannot be deleted because they are associated with existing books. The 
user should be advised to either remove the associated books or reassign them to another book. Reassigning won't be
implemented in the author section.
___
## Technical Details
### Laravel Framework
1. The application will be implemented using the latest stable version of the Laravel framework.
2. Use the Vue started kit to implement the front-end of the application, allowing for a dynamic and responsive user interface.
3. Use Laravel Fortify for authentication and authorization, ensuring that only authorized users can access the application 
and perform actions such as adding, editing, and deleting books and authors. Each user will have a user roles and permissions.
3. The application will use Laravel's Eloquent ORM to handle database interactions.
___

### Folder Structure
```
project-root/
    ├── docs/ -> Documentation for the application
    ├── docker/ -> Docker related files and configurations
        ├── php/ -> Dockerfile and related configurations for the PHP application
        ├── nginx/ -> Dockerfile and related configurations for the Nginx web server
        └── mysql/ -> Dockerfile and related configurations for the MySQL database
    ├── library/ -> Application code, where Laravel framework is implemented
    └── docker-compose.yml -> Docker Compose file to orchestrate the different containers
```
### Dockerization
1. The application will be dockerized to ensure that it can run in different environments without compatibility issues.
2. The Docker setup will include separate containers for the PHP application, Nginx web server, and MySQL database, 
allowing for better separation of concerns and easier maintenance.
   - These images should use the latest stable versions of PHP, Nginx, and MySQL to ensure that the application is running 
on the most up-to-date and secure software, and also using the alpine version of the images to keep the image size small 
and efficient.
3. The Docker configuration will include environment variables for database credentials and other configuration settings, 
allowing for easy customization and deployment in different environments.
4. The Docker setup will also include a volume for the book files, ensuring that the uploaded files are persisted even if 
the containers are recreated or updated.
5. The Docker configuration will include a `docker-compose.yml` file to orchestrate the different containers and make it 
easy to start and stop the application with a single command.
___

### Database Schema
#### Tables
##### books
| column           | type          |
|------------------|---------------|
| id               | (primary key) |
| title            | (string)      |
| publication_year | (integer)     |
| file_path        | (string)      |
| created_at       | (timestamp)   |
| updated_at       | (timestamp)   |

##### authors
| column           | type          |
|------------------|---------------|
| id               | (primary key) |
| name             | (string)      |
| created_at       | (timestamp)   |
| updated_at       | (timestamp)   |

##### authors_books
| column     | type          |
|------------|---------------|
| book_id    | (primary key) |
| author_id  | (primary key) |

1. Remaining tables will be created at Laravel install, such as for Fortify. Check if roles and permissions tables are 
created by default, if not, they will be created as well.
2. Create a seeder to populate the user tables with a default admin user, and also create seeders for books and authors 
to populate the database with some initial data for testing and development purposes. The seeders will use Laravel's 
factory feature to generate realistic and random data for users, roles, permissions, books and authors, making it easier 
to test the application.
___

### Routes
The application will have the following routes:
1. `GET /books` - Display the paginated list of all books in the library.
2. `GET /books/create` - Display the form to add a new book to the library.
3. `POST /books` - Handle the submission of the form to add a new book to the library.
4. `GET /books/{id}/edit` - Display the form to edit an existing book in the library.
5. `PUT /books/{id}` - Handle the submission of the form to update an existing book in the library.
6. `DELETE /books/{id}` - Handle the deletion of a book from the library.
7. `GET /books/{id}/download` - Handle the download of a book file from the library.
8. `GET books/search` - Handle the search for books by title or author in the library.
9. `GET /authors` - Display the paginated list of all authors in the library.
10. `GET /authors/create` - Display the form to add a new author to the library.
11. `POST /authors` - Handle the submission of the form to add a new author to the library.
12. `GET /authors/{id}/edit` - Display the form to edit an existing author in the library.
13. `PUT /authors/{id}` - Handle the submission of the form to update an existing author in the library.
14. `DELETE /authors/{id}` - Handle the deletion of an author from the library.
15. `GET authors/search` - Handle the search for author by name in the library.
16. Remaining routes will be created at Laravel install, such as for Fortify.

___
### Desing Patterns
#### Domain Driven Design (DDD)
1. The application will be designed following the Domain Driven Design (DDD) pattern, separating the concerns of Books,
Authors, and the future media types into different bounded contexts. Each context will have its own models, controllers, 
views, and routes, allowing for better organization and maintainability of the codebase.

#### Object-Oriented Programming (OOP) and SOLID Principles
1. The application will be implemented using object-oriented programming principles, ensuring that the code is modular, 
reusable, and maintainable.
2. The code will follow the SOLID principles to ensure that it is well-structured and easy to understand.
3. The application will use Laravel's Eloquent ORM to handle database interactions, allowing for a clean and efficient 
way to manage the relationships between books and authors.

#### Layers Architecture
1. The application will be structure in layers, separating the concerns of the application into different layers such as:
    - Controllers: Responsible for handling HTTP requests and responses, and coordinating the flow of data between the 
views and services.
    - FormRequests: Responsible for validating the input data from the user and ensuring that it meets the requirements before 
it is processed by the services.
    - Services: Responsible for implementing the business logic of the application, such as handling the creation, updating, 
and deletion of books and authors, as well as handling the search functionality.
    - Repositories: Responsible for handling the data access and interactions with the models, using Laravel's Eloquent 
ORM to manage the relationships between books and authors.
    - Cache: Responsible for caching the results of expensive operations, such as fetching the list of books and author.
This will be implemented using Redis, and it will be an intermediate layer between the services and repositories.
   - Views: Responsible for displaying the user interface and presenting the data to the user, using Blade templates ge number of books and authors.
and Vue components to create a dynamic and responsive user experience
___

### Viwes
The application will have the following views blade files:
1. `books/index.blade.php`
   - This view is embedding the `BookList.vue` component that displays the list of all books in the library, along with 
options to edit, delete, and download each book.
   - This view will handle the data from backend and pass it to the `BookList.vue` component to display the list of books.
   - The pagination will not reload the page, but will be handled by the `Pagination.vue` component to fetch the next 
set of books from the backend and update the list without reloading the page.
2. `books/save.blade.php`
   - This view is embedding the `BookForm.vue` component that provides a form for adding a new book to the library.
3. `authors/index.blade.php`
   - This view is embedding the `AuthorForm.vue` component that displays the list of all authors in the library, along with 
options to edit and delete each author.
   - This view will handle the data from backend and pass it to the  `AutorList.vue` component to display the list of authors.
   - The pagination will not reload the page, but will be handled by the `Pagination.vue` component to fetch the next
     set of authors from the backend and update the list without reloading the page.
4. `authors/save.blade.php`
   - This view is embedding the `AuthorForm.vue` component that provides a form for adding a new author to the library.
___

### Vue Components
1. `components\shared\Pagination.vue`
    - This component is generic, responsible for handling the pagination of any list for all list in the application. It 
will fetch the next set of data from the backend and update the list without reloading the page where it is used. It will 
be used in the `BookList.vue` and `AuthorList.vue` components to handle the pagination of books and authors respectively.
    - This component should be reusable and can be used in different views and components where search functionality is needed.
    - This component will receive the current page, total pages, and a callback function to fetch the next set of data 
as props. It will display pagination controls and handle user interactions to navigate through the pages.
    - This component will be used for the future lists of new media types.
2. `components\book\BookList.vue`
    - This component is responsible for displaying the list of books in the library, along with options to edit, delete, 
and download each book. 
    - This Vue component is a shell that will be used to display the list of books and handle the search functionality.
    - This component also uses the `SearchBar.vue` component to allow users to search for books by title or authors.
    - This component is using the `Pagination.vue` component to fetch and display the next set of books without reloading 
the page.
    - This component is using the `BtnEdit.vue`, `BtnDelete.vue`, and `BtnDownload.vue` components to provide edit, 
delete, and download functionality for each book in the list.
3. `components\author\AuthorList.vue`
    - This component is responsible for displaying the list of authors in the library, along with options to edit and 
delete each author.
    - The component will handle pagination to fetch and display the next set of authors without reloading the page.
    - This component also uses the `SearchBar.vue` component to allow users to search for authors.
    - This component is using the `Pagination.vue` component to fetch and display the next set of books without reloading 
the page.
    - This component is using the `BtnEdit.vue`, and `BtnDelete.vue` components to provide edit, delete functionality 
for each author in the list.
4. `components\book\BookForm.vue`
    - This component provides a form for adding a new book to the library or editing an existing book.
    - The form includes fields for the title, author(s), publication year, and file upload.
    - The author field includes a dropdown list of existing authors with an auto-complete feature, as well as an option 
to add a new author if the desired one is not available.
    - This component is also used to update the book information, including the associations between books and authors 
when editing a book.
5. `components\author\AuthorForm.vue`
    - This component provides a form for adding a new author to the library or editing an existing author.
    - The form includes a field for the name of the author.
    - This component is also used to update the author information when editing an author.
6. `components\shared\SearchBar.vue`
    - This component is generic and provides a search bar for users to search items in a list.
    - This component should be reusable and can be used in different views and components where search functionality is needed.
    - The search bar includes an input field and a search button.
    - The component will handle the search functionality and pass the search query to the parent component to fetch the 
items that match the search query from the backend.
7. `components\shared\BtnDelete.vue`
    - This component is generic and provides a delete button for items in a list.
    - This component should be reusable and can be used in different views and components where delete functionality is needed.
    - The delete button will trigger a confirmation dialog to prevent accidental deletions, and upon confirmation, it will
call a callback function passed as a prop to perform the deletion action.
8. `components\shared\BtnEdit.vue`
    - This component is generic and provides an edit button for items in a list.
    - This component should be reusable and can be used in different views and components where edit functionality is needed.
    - The edit button will trigger a callback function passed as a prop to perform the edit action, such as opening a form
to edit the item details.
9. `components\shared\BtnDownload.vue`
    - This component is generic and provides a download button for each item in the list.
    - This component should be reusable and can be used in different views and components where download functionality is 
needed to fetch the other future types of media.
    - The download button will trigger a callback function passed as a prop to perform the download action, such as
initiating a file download for the file associated with that item.
___
### Refactoring

The application should be better extendable. The author is tightly coupled to books. It'll be a problem when adding other 
media types that will have their own authors.

Refactor the back-end only in the book entrypoints.

A morph table could be used for this purpose. Create a morph Media table with the common fields between all media types, such 
as title, publication year, and file path. Use uuid instead id in the morph columns, to be unique among the types.

The book table will have only the columns uuid column, pages, created_at and updated_at.
The authors table will remain the same, it's not morph, but the pivot table will be refactored to contains media_id and author_id only.

The routes will accept the resource type to distinguish the morph type. 
For example the route /books will be /{type}; /books/{id}/edit will be /{type}/{id}/edit; and so on;
The author routes will be the same.

Create a MediaController as a front controller for all types of media requests. The match type will use your respective 
Domain structure, such as services, repositories, etc. The title, publication year, and file path should be validated in 
this controller, and the specific fields for each media type should be validated in their respective services.

Seeds and factories should be refactored accordingly.