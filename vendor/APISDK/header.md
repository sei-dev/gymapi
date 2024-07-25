**Success Response**


```
/action=crossCheck&emails=["test@test.com"]&hash=er43t..&access_token=...
HTTP/1.1 200 OK
{
  "status": "success",
  "message": "",
  "result": 
  {
  	"email": "test@test.com",
    	"type": "oldtimer",
    	"mark_spam_bounce": "1",
    	"deactivate_reason": "",
    	"sg_data": 
	{
      		"deactivate_reason": "",
      		"book_category": "social-sciences-and-humanities",
		...
    	},
  	"list":[]
  }
}
```


**Fail Response**



```
{
  "status": "fail",
  "message": "Error message",
  "result": 
  {
  	"list":[]
  }
}
```
