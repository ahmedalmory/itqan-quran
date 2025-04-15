---------------------Students----------------------------
SELECT     Users.Name, Users.Email, Users.Age, CircleTypes.Title AS Edara, Circles.Name AS Halaqa, Users_1.Name AS Teacher, Countries.Name AS Expr1, UserGenders.ID, UserGenders.Title, Users.Mobile, Countries.CountryCode
FROM        Users INNER JOIN
                  CircleSubscriptions ON Users.ID = CircleSubscriptions.StudentID INNER JOIN
                  Circles ON CircleSubscriptions.CircleID = Circles.ID INNER JOIN
                  CircleTypes ON Circles.CircleTypeID = CircleTypes.Id INNER JOIN
                  Countries ON Users.Country = Countries.ID INNER JOIN
                  UserGenders ON Users.UserGenderID = UserGenders.ID LEFT OUTER JOIN
                  Users AS Users_1 ON Circles.TeacherID = Users_1.ID
WHERE     (CircleSubscriptions.UnsubscribeDate IS NULL) AND (CircleSubscriptions.SuspendDate IS NULL) AND (Users.UserGenderID = 1)
ORDER BY Edara, Halaqa, Users.Name

---------------------End Students----------------------------

---------------------Teachers----------------------------
	SELECT        Users.Name,  Users.Email, Users.Age, Circles.Name AS Halaqa, CircleTypes.Title AS Edara,  Countries.CountryCode,   Users.Mobile,  Countries.Name as CountryEN
FROM            Users INNER JOIN
                         Circles ON Users.ID = Circles.TeacherID INNER JOIN
                         CircleTypes ON Circles.CircleTypeID = CircleTypes.Id INNER JOIN
                         Countries ON Users.Country = Countries.ID
WHERE        (Users.UserTypeID = 2) AND (CircleTypes.IsForFemales = 0)
ORDER BY Edara, Users.Name

---------------------End Teachers----------------------------

---------------------Supervisors----------------------------
SELECT     Users.Name, Users.Email, Users.Age, Circles.Name AS Halaqa, CircleTypes.Title AS Edara, Countries.CountryCode, Users.Mobile, Countries.Name AS CountryEN
FROM        Countries INNER JOIN
                  Users ON Countries.ID = Users.Country INNER JOIN
                  CircleTypes INNER JOIN
                  Circles ON CircleTypes.Id = Circles.CircleTypeID ON Users.ID = Circles.SupervisorID
WHERE     (Users.UserTypeID = 3) AND (CircleTypes.IsForFemales = 0)
ORDER BY Edara, Users.Name

---------------------End Supervisors----------------------------
